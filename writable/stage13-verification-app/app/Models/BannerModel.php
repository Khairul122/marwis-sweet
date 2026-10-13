<?php
namespace App\Models;
use CodeIgniter\Model;
class BannerModel extends Model
{
    protected $table = 'banners';
    protected $returnType = 'array';
    protected $allowedFields = ['title', 'image_path', 'alt_text', 'link_url', 'sort_order', 'starts_at', 'ends_at', 'is_active'];
    protected $useTimestamps = true;
}
