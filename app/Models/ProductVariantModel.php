<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductVariantModel extends Model
{
    protected $table = 'product_variants';
    protected $returnType = 'array';
    protected $allowedFields = ['product_id', 'name', 'sku', 'price', 'stock_mode', 'is_default', 'is_active'];
    protected $useTimestamps = true;
}
