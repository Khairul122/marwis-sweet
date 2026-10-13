<?php

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Models\BannerModel;
use App\Models\SettingModel;

class Home extends BaseController
{
    public function index(): string
    {
        $settings = [];
        foreach ((new SettingModel())->findAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $now = date('Y-m-d H:i:s');
        $banners = (new BannerModel())
            ->where('is_active', true)
            ->groupStart()->where('starts_at', null)->orWhere('starts_at <=', $now)->groupEnd()
            ->groupStart()->where('ends_at', null)->orWhere('ends_at >', $now)->groupEnd()
            ->orderBy('sort_order')->orderBy('id', 'DESC')->findAll();
        $categories = (new CategoryModel())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->findAll();
        $products = (new ProductModel())
            ->select('products.*, categories.name AS category_name, categories.slug AS category_slug')
            ->join('categories', 'categories.id = products.category_id')
            ->where('products.is_active', true)
            ->where('categories.is_active', true)
            ->orderBy('products.is_featured', 'DESC')
            ->orderBy('products.name')
            ->findAll();
        $variants = (new ProductVariantModel())
            ->where('is_active', true)
            ->orderBy('is_default', 'DESC')
            ->orderBy('price')
            ->findAll();
        $variantsByProduct = [];
        foreach ($variants as $variant) {
            $variantsByProduct[$variant['product_id']][] = $variant;
        }

        return view('public/home', [
            'businessName' => $settings['business_name'] ?? env('BUSINESS_NAME', 'Marwis Sweet'),
            'settings' => $settings,
            'banners' => $banners,
            'categories' => $categories,
            'products' => $products,
            'variantsByProduct' => $variantsByProduct,
        ]);
    }

    public function product(string $slug): string
    {
        $product = (new ProductModel())
            ->select('products.*, categories.name AS category_name')
            ->join('categories', 'categories.id = products.category_id')
            ->where('products.slug', $slug)
            ->where('products.is_active', true)
            ->where('categories.is_active', true)
            ->first();
        if (!$product) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Produk tidak ditemukan.');
        }
        $settings = [];
        foreach ((new SettingModel())->findAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $database = db_connect();
        $reviews = $database->table('product_reviews')->where('product_id', $product['id'])->where('status', 'approved')->orderBy('created_at', 'DESC')->limit(20)->get()->getResultArray();
        $reviewImages = [];
        if ($reviews !== []) { foreach ($database->table('review_images')->whereIn('product_review_id', array_column($reviews, 'id'))->get()->getResultArray() as $image) { $reviewImages[$image['product_review_id']][] = $image; } }
        $reviewSummary = $database->table('product_reviews')->select('COUNT(*) AS total, COALESCE(AVG(rating), 0) AS average', false)->where('product_id', $product['id'])->where('status', 'approved')->get()->getRowArray();
        return view('public/product', [
            'businessName' => $settings['business_name'] ?? env('BUSINESS_NAME', 'Marwis Sweet'),
            'settings' => $settings,
            'product' => $product,
            'variants' => (new ProductVariantModel())->where('product_id', $product['id'])->where('is_active', true)->orderBy('is_default', 'DESC')->orderBy('price')->findAll(),
            'images' => $database->table('product_images')->where('product_id', $product['id'])->orderBy('sort_order')->get()->getResultArray(),
            'reviews' => $reviews, 'reviewImages' => $reviewImages, 'reviewSummary' => $reviewSummary,
        ]);
    }
}
