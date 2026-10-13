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
        $ids = array_values(array_unique(array_filter(array_map('intval', $inventoryItemIds))));
        if ($ids === []) {
            return;
        }
        $locked = $this->database->table('stock_opname_items')
            ->select('inventory_items.name, stock_opnames.opname_number')
            ->join('stock_opnames', 'stock_opnames.id = stock_opname_items.stock_opname_id')
            ->join('inventory_items', 'inventory_items.id = stock_opname_items.inventory_item_id')
            ->whereIn('stock_opname_items.inventory_item_id', $ids)
            ->whereIn('stock_opnames.status', ['counting', 'submitted', 'rejected'])
            ->get()->getRowArray();
        if ($locked) {
            throw new RuntimeException('Stok ' . $locked['name'] . ' sedang dikunci oleh opname ' . $locked['opname_number'] . '.');
        }
    }
}
