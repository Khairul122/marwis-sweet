<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table = 'products';
    protected $returnType = 'array';
    protected $allowedFields = ['category_id', 'name', 'slug', 'description', 'product_type', 'image_path', 'is_featured', 'is_active'];
    protected $useTimestamps = true;
}
