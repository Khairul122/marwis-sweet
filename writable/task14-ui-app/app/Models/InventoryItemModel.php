<?php
namespace App\Models;
use CodeIgniter\Model;
class InventoryItemModel extends Model
{
    protected $table = 'inventory_items';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'sku', 'base_unit', 'quantity_on_hand', 'average_cost', 'minimum_stock', 'is_active'];
    protected $useTimestamps = true;
}
