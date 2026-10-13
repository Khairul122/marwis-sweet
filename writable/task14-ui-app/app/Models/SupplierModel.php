<?php
namespace App\Models;
use CodeIgniter\Model;
class SupplierModel extends Model
{
    protected $table = 'suppliers';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'contact_name', 'phone', 'address', 'notes', 'is_active'];
    protected $useTimestamps = true;
}
