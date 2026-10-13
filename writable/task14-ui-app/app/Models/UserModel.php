<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'username', 'email', 'password_hash', 'role', 'status', 'last_login_at'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
