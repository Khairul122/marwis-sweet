<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Menyusun usulan bahan yang perlu dihitung fisik. Hanya membuat usulan; sesi opname
 * (dan penguncian stok) baru dibuat saat staf memilih "Mulai opname".
 */
class OpnameSuggestionService
{
    public const STALE_DAYS = 7;
    public const ACTIVITY_RATIO = 0.5;

    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    /** @return int Jumlah usulan baru. */
    public function generate(): int
    {
        $now = date('Y-m-d H:i:s');
        $lastCounts = array_column($this->database->query(
            "SELECT soi.inventory_item_id AS id, MAX(so.approved_at) AS last_count FROM stock_opname_items soi
             JOIN stock_opnames so ON so.id = soi.stock_opname_id WHERE so.status = 'approved' GROUP BY soi.inventory_item_id"
        )->getResultArray(), 'last_count', 'id');
        $busy = array_map('intval', array_column($this->database->query(
            "SELECT DISTINCT soi.inventory_item_id AS id FROM stock_opname_items soi JOIN stock_opnames so ON so.id = soi.stock_opname_id
             WHERE so.status IN ('counting','submitted','rejected')
             UNION SELECT inventory_item_id FROM opname_suggestions WHERE status = 'open'"
        )->getResultArray(), 'id'));

        $created = 0;
        foreach ($this->database->table('inventory_items')->where('is_active', true)->get()->getResultArray() as $item) {
            if (in_array((int) $item['id'], $busy, true)) {
                continue;
            }
            $since = $lastCounts[$item['id']] ?? $item['created_at'] ?? $now;
            $reasons = [];
            if ((float) $item['quantity_on_hand'] <= (float) $item['minimum_stock']) {
                $reasons['low_stock'] = 'Stok ' . $this->qty($item['quantity_on_hand']) . ' ' . $item['base_unit'] . ' (minimum ' . $this->qty($item['minimum_stock']) . ')';
            }
            // ponytail: satu query per bahan; ganti GROUP BY tunggal bila bahan mencapai ribuan.
            $moved = (float) ($this->database->table('stock_movements')->selectSum('ABS(quantity)', 'moved')->where('inventory_item_id', $item['id'])
                ->whereIn('movement_type', ['sale', 'return'])->where('posted_at >', $since)->get()->getRow('moved') ?? 0);
            $pool = (float) $item['quantity_on_hand'] + $moved;
            if ($moved > 0 && $pool > 0 && $moved / $pool >= self::ACTIVITY_RATIO) {
                $reasons['activity'] = 'Terpakai ' . $this->qty($moved) . ' ' . $item['base_unit'] . ' dari penjualan/retur sejak hitung terakhir';
            }
            if (strtotime($since) <= strtotime('-' . self::STALE_DAYS . ' days')) {
                $reasons['stale'] = isset($lastCounts[$item['id']]) ? 'Terakhir dihitung ' . date('d/m/Y', strtotime($since)) : 'Belum pernah dihitung';
            }
            if ($reasons === []) {
                continue;
            }
            $this->database->table('opname_suggestions')->insert([
                'inventory_item_id' => $item['id'],
                'reason' => array_key_first($reasons),
                'detail' => mb_substr(implode('; ', $reasons), 0, 300),
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $created++;
        }

        return $created;
    }

    /** @param list<int> $suggestionIds */
    public function start(array $suggestionIds, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $suggestionIds))));
        $rows = $ids === [] ? [] : $this->database->table('opname_suggestions')->whereIn('id', $ids)->where('status', 'open')->get()->getResultArray();
        if ($rows === []) {
            throw new RuntimeException('Pilih minimal satu usulan yang masih terbuka.');
        }
        $opnameId = (new StockOpnameService($this->database))->create([
            'scope' => 'partial',
            'item_ids' => array_column($rows, 'inventory_item_id'),
            'notes' => 'Dibuat dari usulan otomatis.',
        ], $userId);
        $now = date('Y-m-d H:i:s');
        $this->database->table('opname_suggestions')->whereIn('id', array_column($rows, 'id'))
            ->update(['status' => 'started', 'stock_opname_id' => $opnameId, 'resolved_by' => $userId, 'resolved_at' => $now, 'updated_at' => $now]);

        return $opnameId;
    }

    public function dismiss(int $suggestionId, int $userId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->table('opname_suggestions')->where('id', $suggestionId)->where('status', 'open')
            ->update(['status' => 'dismissed', 'resolved_by' => $userId, 'resolved_at' => $now, 'updated_at' => $now]);
    }

    private function qty($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',') ?: '0';
    }
}
