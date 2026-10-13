<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class InventoryLockService
{
    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    public function assertUnlocked(array $inventoryItemIds): void
    {
        $locked = $this->findLocks($inventoryItemIds);
        if ($locked !== []) {
            $first = reset($locked);
            throw new RuntimeException('Stok ' . $first['name'] . ' sedang dikunci oleh opname ' . $first['opname_number'] . '.');
        }
    }

    /** @return array<int, array{name:string, opname_number:string}> keyed by inventory item ID */
    public function findLocks(array $inventoryItemIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $inventoryItemIds))));
        if ($ids === []) {
            return [];
        }

        $rows = $this->database->table('stock_opname_items')
            ->select('stock_opname_items.inventory_item_id, inventory_items.name, stock_opnames.opname_number')
            ->join('stock_opnames', 'stock_opnames.id = stock_opname_items.stock_opname_id')
            ->join('inventory_items', 'inventory_items.id = stock_opname_items.inventory_item_id')
            ->whereIn('stock_opname_items.inventory_item_id', $ids)
            ->whereIn('stock_opnames.status', ['counting', 'submitted', 'rejected'])
            ->orderBy('stock_opnames.id')
            ->get()->getResultArray();

        $locked = [];
        foreach ($rows as $row) {
            $locked[(int) $row['inventory_item_id']] ??= [
                'name' => $row['name'],
                'opname_number' => $row['opname_number'],
            ];
        }

        return $locked;
    }
}
