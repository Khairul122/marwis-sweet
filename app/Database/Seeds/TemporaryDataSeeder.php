<?php

namespace App\Database\Seeds;

use App\Services\Money;
use CodeIgniter\Database\Seeder;
use LogicException;

class TemporaryDataSeeder extends Seeder
{
    public function run(): void
    {
        if (ENVIRONMENT === 'production') {
            throw new LogicException('TemporaryDataSeeder hanya boleh dijalankan di lingkungan development atau testing.');
        }

        $this->db->transException(true)->transStart();

        $this->seedSettings();
        $this->upsert('suppliers', ['name' => 'Supplier Demo Utama'], [
            'contact_name' => 'Budi Demo',
            'phone' => '081234567890',
            'address' => 'Alamat contoh untuk pengembangan',
            'notes' => 'DATA DEMO — ganti sebelum digunakan untuk operasional.',
            'is_active' => true,
        ]);

        $ingredients = [
            'DEMO-TEPUNG' => ['Tepung terigu', 'gram', 25000, 14, 5000],
            'DEMO-TELUR' => ['Telur ayam', 'butir', 240, 2200, 30],
            'DEMO-GULA' => ['Gula pasir', 'gram', 10000, 18, 2000],
            'DEMO-MENTEGA' => ['Mentega', 'gram', 8000, 45, 1500],
            'DEMO-KEJU' => ['Keju', 'gram', 6000, 95, 1000],
            'DEMO-COKELAT' => ['Cokelat meses', 'gram', 7000, 55, 1000],
        ];
        $ingredientIds = [];
        foreach ($ingredients as $sku => [$name, $unit, $quantity, $cost, $minimum]) {
            $ingredientIds[$sku] = $this->upsert('inventory_items', ['sku' => $sku], [
                'name' => $name,
                'base_unit' => $unit,
                'quantity_on_hand' => $quantity,
                'average_cost' => $cost,
                'inventory_value' => Money::decimal(Money::centsForQuantity($quantity * 1000, $cost * 10000)),
                'minimum_stock' => $minimum,
                'is_active' => true,
            ]);
        }

        foreach ([
            ['DEMO-TEPUNG', 'kg', 1000],
            ['DEMO-GULA', 'kg', 1000],
            ['DEMO-MENTEGA', 'kg', 1000],
            ['DEMO-KEJU', 'kg', 1000],
            ['DEMO-COKELAT', 'kg', 1000],
            ['DEMO-TELUR', 'tray', 30],
        ] as [$sku, $unit, $factor]) {
            $this->upsert('unit_conversions', [
                'inventory_item_id' => $ingredientIds[$sku],
                'purchase_unit' => $unit,
            ], ['factor_to_base' => $factor]);
        }

        $sweetCategoryId = $this->upsert('categories', ['slug' => 'demo-martabak-manis'], [
            'name' => 'Martabak Manis',
            'description' => 'Pilihan martabak manis untuk data demo.',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $eggCategoryId = $this->upsert('categories', ['slug' => 'demo-martabak-telur'], [
            'name' => 'Martabak Telur',
            'description' => 'Pilihan martabak telur untuk data demo.',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $products = [
            ['demo-martabak-manis-original', $sweetCategoryId, 'Martabak Manis Original', 'Adonan lembut dengan mentega dan gula.', true, [
                ['DEMO-MM-ORI', 'Regular', 28000, ['DEMO-TEPUNG' => 180, 'DEMO-TELUR' => 1, 'DEMO-GULA' => 45, 'DEMO-MENTEGA' => 30]],
                ['DEMO-MM-ORI-L', 'Large', 42000, ['DEMO-TEPUNG' => 280, 'DEMO-TELUR' => 2, 'DEMO-GULA' => 70, 'DEMO-MENTEGA' => 45]],
            ]],
            ['demo-martabak-manis-cokelat-keju', $sweetCategoryId, 'Martabak Cokelat Keju', 'Perpaduan meses cokelat dan keju.', true, [
                ['DEMO-MM-CK', 'Regular', 38000, ['DEMO-TEPUNG' => 180, 'DEMO-TELUR' => 1, 'DEMO-GULA' => 45, 'DEMO-MENTEGA' => 30, 'DEMO-KEJU' => 60, 'DEMO-COKELAT' => 50]],
            ]],
            ['demo-martabak-telur-spesial', $eggCategoryId, 'Martabak Telur Spesial', 'Martabak telur gurih untuk data demo.', false, [
                ['DEMO-MT-SP', '3 Telur', 45000, ['DEMO-TEPUNG' => 120, 'DEMO-TELUR' => 3, 'DEMO-MENTEGA' => 15]],
            ]],
        ];

        foreach ($products as [$slug, $categoryId, $name, $description, $featured, $variants]) {
            $productId = $this->upsert('products', ['slug' => $slug], [
                'category_id' => $categoryId,
                'name' => $name,
                'description' => $description,
                'product_type' => 'prepared',
                'image_path' => null,
                'is_featured' => $featured,
                'is_active' => true,
            ]);

            foreach ($variants as $index => [$sku, $variantName, $price, $recipe]) {
                $variantId = $this->upsert('product_variants', ['sku' => $sku], [
                    'product_id' => $productId,
                    'name' => $variantName,
                    'price' => $price,
                    'stock_mode' => 'recipe',
                    'direct_inventory_item_id' => null,
                    'is_default' => $index === 0,
                    'is_active' => true,
                ]);
                $recipeId = $this->upsert('recipes', ['product_variant_id' => $variantId, 'version' => 1], ['is_active' => true]);
                foreach ($recipe as $ingredientSku => $quantity) {
                    $this->upsert('recipe_items', [
                        'recipe_id' => $recipeId,
                        'inventory_item_id' => $ingredientIds[$ingredientSku],
                    ], ['quantity' => $quantity]);
                }
            }
        }

        $toppings = [
            ['DEMO-TOP-KEJU', 'Ekstra keju', 8000, 'DEMO-KEJU', 50],
            ['DEMO-TOP-COKELAT', 'Ekstra cokelat', 6000, 'DEMO-COKELAT', 45],
        ];
        foreach ($toppings as [$sku, $name, $price, $ingredientSku, $quantity]) {
            $toppingId = $this->upsert('toppings', ['sku' => $sku], [
                'name' => $name,
                'additional_price' => $price,
                'is_active' => true,
            ]);
            $this->upsert('topping_recipe_items', [
                'topping_id' => $toppingId,
                'inventory_item_id' => $ingredientIds[$ingredientSku],
            ], ['quantity' => $quantity]);
        }

        $this->db->transComplete();
    }

    private function seedSettings(): void
    {
        foreach ([
            'business_name' => 'Marwis Sweet Demo',
            'address' => 'Jl. Contoh No. 10, Indonesia',
            'phone' => '0812-3456-7890',
            'opening_hours' => 'Senin–Minggu, 16.00–23.00',
        ] as $key => $value) {
            $this->upsert('settings', ['setting_key' => $key], ['setting_value' => $value], false);
        }
    }

    /**
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $values
     */
    private function upsert(string $table, array $identity, array $values, bool $timestamps = true): int
    {
        $builder = $this->db->table($table);
        $existing = $builder->where($identity)->get()->getRowArray();
        $now = date('Y-m-d H:i:s');
        if ($timestamps && $this->db->fieldExists('updated_at', $table)) {
            $values['updated_at'] = $now;
        }

        if ($existing !== null) {
            $builder->where($identity)->update($values);
            return (int) ($existing['id'] ?? 0);
        }

        $payload = array_merge($identity, $values);
        if ($timestamps && $this->db->fieldExists('created_at', $table)) {
            $payload['created_at'] = $now;
        }
        $builder->insert($payload);

        return (int) $this->db->insertID();
    }
}
