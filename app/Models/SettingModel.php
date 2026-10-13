<?php
namespace App\Models;
use CodeIgniter\Model;
class SettingModel extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'setting_key';
    protected $returnType = 'array';
    protected $allowedFields = ['setting_key', 'setting_value', 'updated_at'];
    protected $useAutoIncrement = false;
    protected $useTimestamps = false;
}
