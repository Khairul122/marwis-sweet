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
        $data = $this->storefront();
        $now = date('Y-m-d H:i:s');
        $data['banners'] = (new BannerModel())
            ->where('is_active', true)
            ->whereNotLike('image_path', 'uploads/demo/%')
            ->groupStart()->where('starts_at', null)->orWhere('starts_at <=', $now)->groupEnd()
            ->groupStart()->where('ends_at', null)->orWhere('ends_at >', $now)->groupEnd()
            ->orderBy('sort_order')->orderBy('id', 'DESC')->findAll();
        $data['categories'] = (new CategoryModel())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->findAll();
        $data['reviews'] = db_connect()->table('product_reviews')
            ->select('product_reviews.display_name, product_reviews.rating, product_reviews.comment, product_reviews.created_at, products.name AS product_name')
            ->join('products', 'products.id = product_reviews.product_id')
            ->join('review_tokens', 'review_tokens.id = product_reviews.review_token_id')
            ->where('product_reviews.status', 'approved')->where('products.is_active', true)
            ->groupStart()->where('review_tokens.disabled_reason', null)->orWhere('review_tokens.disabled_reason !=', 'EVL contoh')->groupEnd()
            ->orderBy('product_reviews.created_at', 'DESC')->limit(6)->get()->getResultArray();

        return view('public/home', $data);
    }

    public function product(string $slug): string
    {
        $data = $this->storefront();
        $product = null;
        foreach ($data['products'] as $candidate) {
            if ($candidate['slug'] === $slug) {
                $product = $candidate;
                break;
            }
        }
        if ($product === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Produk tidak ditemukan.');
        }

        $database = db_connect();
        $reviews = $database->table('product_reviews')->select('product_reviews.*')
            ->join('review_tokens', 'review_tokens.id = product_reviews.review_token_id')
            ->where('product_reviews.product_id', $product['id'])->where('product_reviews.status', 'approved')
            ->groupStart()->where('review_tokens.disabled_reason', null)->orWhere('review_tokens.disabled_reason !=', 'EVL contoh')->groupEnd()
            ->orderBy('product_reviews.created_at', 'DESC')->limit(20)->get()->getResultArray();
        $reviewImages = [];
        if ($reviews !== []) {
            foreach ($database->table('review_images')->whereIn('product_review_id', array_column($reviews, 'id'))->get()->getResultArray() as $image) {
                $reviewImages[$image['product_review_id']][] = $image;
            }
        }
        $ratingCounts = array_fill_keys([5, 4, 3, 2, 1], 0);
        foreach ($database->table('product_reviews')->select('product_reviews.rating, COUNT(*) AS total', false)
            ->join('review_tokens', 'review_tokens.id = product_reviews.review_token_id')
            ->where('product_reviews.product_id', $product['id'])->where('product_reviews.status', 'approved')
            ->groupStart()->where('review_tokens.disabled_reason', null)->orWhere('review_tokens.disabled_reason !=', 'EVL contoh')->groupEnd()
            ->groupBy('product_reviews.rating')->get()->getResultArray() as $row) {
            $ratingCounts[(int) $row['rating']] = (int) $row['total'];
        }
        $total = array_sum($ratingCounts);

        // Menu lain: kategori yang sama didahulukan
        $others = array_values(array_filter($data['products'], static fn (array $p): bool => (int) $p['id'] !== (int) $product['id']));
        usort($others, static fn (array $a, array $b): int => ($b['category_id'] === $product['category_id']) <=> ($a['category_id'] === $product['category_id']));

        return view('public/product', $data + [
            'product' => $product,
            'variants' => $data['variantsByProduct'][$product['id']] ?? [],
            'images' => $database->table('product_images')->where('product_id', $product['id'])->orderBy('sort_order')->get()->getResultArray(),
            'reviews' => $reviews,
            'reviewImages' => $reviewImages,
            'ratingCounts' => $ratingCounts,
            'reviewSummary' => [
                'total' => $total,
                'average' => $total > 0 ? array_sum(array_map(static fn (int $star, int $n): int => $star * $n, array_keys($ratingCounts), $ratingCounts)) / $total : 0,
            ],
            'related' => array_slice($others, 0, 4),
        ]);
    }

    /** Data bersama halaman publik: identitas toko, kontak, menu aktif, dan katalog untuk keranjang (landing.js). */
    private function storefront(): array
    {
        helper('storefront');
        $settings = [];
        foreach ((new SettingModel())->findAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $businessName = $settings['business_name'] ?? env('BUSINESS_NAME', 'Marwis Sweet');
        $phone = $settings['phone'] ?? '';
        $waNumber = preg_replace('/^0/', '62', preg_replace('/\D/', '', $phone));

        $products = (new ProductModel())
            ->select('products.*, categories.name AS category_name, categories.slug AS category_slug')
            ->join('categories', 'categories.id = products.category_id')
            ->where('products.is_active', true)
            ->where('categories.is_active', true)
            ->orderBy('products.is_featured', 'DESC')
            ->orderBy('products.name')
            ->findAll();
        $database = db_connect();
        $availability = new \App\Services\ProductAvailabilityService($database);
        $variants = $availability->annotateVariants((new ProductVariantModel())->where('is_active', true)->orderBy('is_default', 'DESC')->orderBy('price')->findAll());
        $variantsByProduct = [];
        foreach ($variants as $variant) {
            $variantsByProduct[$variant['product_id']][] = $variant;
        }

        $toppings = $availability->annotateToppings($database->table('toppings')->where('is_active', true)->orderBy('additional_price')->orderBy('name')->get()->getResultArray());
        $ratings = [];
        foreach ($database->table('product_reviews')->select('product_reviews.product_id, AVG(product_reviews.rating) AS average, COUNT(*) AS total', false)
            ->join('review_tokens', 'review_tokens.id = product_reviews.review_token_id')
            ->where('product_reviews.status', 'approved')
            ->groupStart()->where('review_tokens.disabled_reason', null)->orWhere('review_tokens.disabled_reason !=', 'EVL contoh')->groupEnd()
            ->groupBy('product_reviews.product_id')->get()->getResultArray() as $row) {
            $ratings[$row['product_id']] = ['average' => (float) $row['average'], 'total' => (int) $row['total']];
        }

        return [
            'settings' => $settings,
            'businessName' => $businessName,
            'phone' => $phone,
            'phoneDigits' => preg_replace('/[^0-9+]/', '', $phone),
            'waNumber' => $waNumber,
            'waChat' => $waNumber !== '' ? 'https://wa.me/' . $waNumber . '?text=' . rawurlencode('Halo ' . $businessName . ', saya mau tanya menu martabak.') : '',
            'address' => $settings['address'] ?? '',
            'hours' => $settings['opening_hours'] ?? '',
            'hasContact' => $phone !== '' || ($settings['address'] ?? '') !== '' || ($settings['opening_hours'] ?? '') !== '',
            'products' => $products,
            'variantsByProduct' => $variantsByProduct,
            'toppings' => $toppings,
            'ratings' => $ratings,
            'storeReviews' => $database->table('product_reviews')->select('COUNT(*) AS total, COALESCE(AVG(product_reviews.rating), 0) AS average', false)
                ->join('review_tokens', 'review_tokens.id = product_reviews.review_token_id')
                ->where('product_reviews.status', 'approved')
                ->groupStart()->where('review_tokens.disabled_reason', null)->orWhere('review_tokens.disabled_reason !=', 'EVL contoh')->groupEnd()->get()->getRowArray(),
            'isDemoData' => ($settings['business_profile_is_demo'] ?? '0') === '1',
            'catalog' => [
                'business' => $businessName,
                'whatsapp' => $waNumber,
                'toppings' => array_map(static fn (array $t): array => ['id' => (int) $t['id'], 'name' => $t['name'], 'price' => (float) $t['additional_price'], 'image' => sf_topping_image($t['name']), 'available' => (bool) $t['is_available']], $toppings),
                'products' => array_map(static fn (array $p): array => [
                    'id' => (int) $p['id'], 'name' => $p['name'],
                    'variants' => array_map(static fn (array $v): array => ['id' => (int) $v['id'], 'name' => $v['name'], 'price' => (float) $v['price'], 'recipe' => $v['stock_mode'] === 'recipe', 'available' => (bool) $v['is_available']], $variantsByProduct[$p['id']] ?? []),
                ], $products),
            ],
        ];
    }
}
