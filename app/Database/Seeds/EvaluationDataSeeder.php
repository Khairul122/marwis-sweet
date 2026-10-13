<?php

namespace App\Database\Seeds;

use App\Services\AccountingService;
use App\Services\ExpenseService;
use App\Services\Money;
use App\Services\SaleService;
use CodeIgniter\Database\Seeder;
use LogicException;

/**
 * Data contoh lengkap untuk evaluasi fitur (katalog, stok, POS, laporan, pengeluaran, ulasan).
 * Hanya untuk development. Aman dijalankan ulang: data ditandai SKU/nomor berawalan EVL dan dilewati bila sudah ada.
 *
 *   php spark db:seed EvaluationDataSeeder
 *
 * Gambar dibuat dengan GD (ilustrasi bertanda "Gambar contoh"), bukan foto produk asli.
 */
class EvaluationDataSeeder extends Seeder
{
    private const DAYS = 14;

    public function run(): void
    {
        if (ENVIRONMENT === 'production') {
            throw new LogicException('EvaluationDataSeeder hanya boleh dijalankan di lingkungan development atau testing.');
        }
        mt_srand(20261013);

        $busy = $this->db->table('stock_opnames')->whereIn('status', ['counting', 'submitted', 'rejected'])->countAllResults();
        if ($busy > 0) {
            echo "Dibatalkan, tidak ada data yang diubah. Selesaikan atau batalkan dulu {$busy} sesi stok opname aktif (sesi itu mengunci stok dan data stoknya akan tidak cocok).
";

            return;
        }

        if ($this->db->table('categories')->where('slug', 'demo-martabak-manis')->countAllResults() === 0) {
            $this->call(TemporaryDataSeeder::class);
        }
        $ids = $this->masterData();
        $this->postedReceipt($ids);
        $this->draftReceipts($ids);
        $this->sales();
        $this->expenses();
        $this->reviews();
        echo "Data evaluasi selesai.\n";
    }

    // ------------------------------------------------------------------ master data

    /** @return array{items: array<string,int>, variants: array<string,int>, toppings: array<string,int>, suppliers: array<string,int>} */
    private function masterData(): array
    {
        $suppliers = [
            'main' => $this->upsert('suppliers', ['name' => 'Supplier Demo Utama'], []),
            'kue' => $this->upsert('suppliers', ['name' => 'Toko Bahan Kue Sentosa'], ['contact_name' => 'Ibu Rina', 'phone' => '081310001001', 'address' => 'Pasar Baru Blok C-12 (data contoh)', 'is_active' => true]),
            'telur' => $this->upsert('suppliers', ['name' => 'UD Telur Segar Makmur'], ['contact_name' => 'Pak Jaya', 'phone' => '081310001002', 'address' => 'Jl. Peternakan No. 4 (data contoh)', 'is_active' => true]),
        ];

        $items = [];
        foreach (['DEMO-TEPUNG', 'DEMO-TELUR', 'DEMO-GULA', 'DEMO-MENTEGA', 'DEMO-KEJU', 'DEMO-COKELAT'] as $sku) {
            $items[$sku] = (int) $this->db->table('inventory_items')->where('sku', $sku)->get()->getRow('id');
        }
        foreach ([
            'EVL-SUSU' => ['Susu kental manis', 'gram', 28, 1000],
            'EVL-KACANG' => ['Kacang tanah', 'gram', 40, 800],
            'EVL-WIJEN' => ['Wijen', 'gram', 60, 300],
            'EVL-DAGING' => ['Daging sapi cincang', 'gram', 120, 800],
            'EVL-AIR' => ['Air mineral 600 ml', 'pcs', 3000, 24],
        ] as $sku => [$name, $unit, $cost, $minimum]) {
            $items[$sku] = $this->upsert('inventory_items', ['sku' => $sku], ['name' => $name, 'base_unit' => $unit, 'minimum_stock' => $minimum, 'is_active' => true], ['quantity_on_hand' => 0, 'average_cost' => 0, 'inventory_value' => '0.00']);
        }
        foreach ([['EVL-SUSU', 'kg', 1000], ['EVL-KACANG', 'kg', 1000], ['EVL-WIJEN', 'kg', 1000], ['EVL-DAGING', 'kg', 1000], ['EVL-AIR', 'dus', 24]] as [$sku, $unit, $factor]) {
            $this->upsert('unit_conversions', ['inventory_item_id' => $items[$sku], 'purchase_unit' => $unit], ['factor_to_base' => $factor]);
        }

        $cats = [
            'sweet' => (int) $this->db->table('categories')->where('slug', 'demo-martabak-manis')->get()->getRow('id'),
            'egg' => (int) $this->db->table('categories')->where('slug', 'demo-martabak-telur')->get()->getRow('id'),
            'mini' => $this->upsert('categories', ['slug' => 'evl-martabak-mini'], ['name' => 'Martabak Mini', 'description' => 'Porsi kecil untuk camilan (data contoh).', 'sort_order' => 30, 'is_active' => true]),
            'drink' => $this->upsert('categories', ['slug' => 'evl-minuman'], ['name' => 'Minuman', 'description' => 'Minuman dingin kemasan (data contoh).', 'sort_order' => 40, 'is_active' => true]),
        ];

        $base = ['DEMO-TEPUNG' => 180, 'DEMO-TELUR' => 1, 'DEMO-GULA' => 45, 'DEMO-MENTEGA' => 30];
        $mini = ['DEMO-TEPUNG' => 45, 'DEMO-GULA' => 12, 'DEMO-MENTEGA' => 8];
        // slug => [category, name, description, featured, active, palette, kind, variants]
        $products = [
            'evl-manis-kacang-wijen' => ['sweet', 'Martabak Kacang Wijen', 'Taburan kacang tanah sangrai dan wijen di atas adonan hangat.', true, true, ['bg' => 'F6D365', 'top' => '8B5A2B'], 'big', [
                ['EVL-MM-KW', 'Regular', 32000, $base + ['EVL-KACANG' => 40, 'EVL-WIJEN' => 10]],
                ['EVL-MM-KW-L', 'Large', 48000, ['DEMO-TEPUNG' => 280, 'DEMO-TELUR' => 2, 'DEMO-GULA' => 70, 'DEMO-MENTEGA' => 45, 'EVL-KACANG' => 60, 'EVL-WIJEN' => 15]],
            ]],
            'evl-manis-susu-cokelat' => ['sweet', 'Martabak Susu Cokelat', 'Meses cokelat dan susu kental manis, manis dan lembut.', true, true, ['bg' => 'E9B384', 'top' => '4B2E1E'], 'big', [
                ['EVL-MM-SC', 'Regular', 36000, $base + ['EVL-SUSU' => 30, 'DEMO-COKELAT' => 40]],
                ['EVL-MM-SC-L', 'Large', 54000, ['DEMO-TEPUNG' => 280, 'DEMO-TELUR' => 2, 'DEMO-GULA' => 70, 'DEMO-MENTEGA' => 45, 'EVL-SUSU' => 45, 'DEMO-COKELAT' => 60]],
            ]],
            'evl-telur-daging' => ['egg', 'Martabak Telur Daging', 'Isi daging sapi cincang, telur, dan bumbu rempah.', false, true, ['bg' => 'F4A261', 'top' => 'B5651D'], 'big', [
                ['EVL-MT-DG', '2 Telur', 52000, ['DEMO-TEPUNG' => 120, 'DEMO-TELUR' => 2, 'DEMO-MENTEGA' => 15, 'EVL-DAGING' => 80]],
                ['EVL-MT-DG-4', '4 Telur', 78000, ['DEMO-TEPUNG' => 160, 'DEMO-TELUR' => 4, 'DEMO-MENTEGA' => 20, 'EVL-DAGING' => 120]],
            ]],
            'evl-mini-cokelat' => ['mini', 'Mini Martabak Cokelat', 'Potongan mini dengan meses cokelat, cocok untuk berbagi.', true, true, ['bg' => 'D4A5A5', 'top' => '3E2723'], 'mini', [
                ['EVL-MN-CK4', 'Isi 4', 18000, $mini + ['DEMO-COKELAT' => 15]],
                ['EVL-MN-CK8', 'Isi 8', 32000, ['DEMO-TEPUNG' => 90, 'DEMO-GULA' => 24, 'DEMO-MENTEGA' => 16, 'DEMO-COKELAT' => 30]],
            ]],
            'evl-mini-keju' => ['mini', 'Mini Martabak Keju', 'Potongan mini dengan keju parut melimpah.', false, true, ['bg' => 'FFE29A', 'top' => 'F2C14E'], 'mini', [
                ['EVL-MN-KJ4', 'Isi 4', 20000, $mini + ['DEMO-KEJU' => 20]],
                ['EVL-MN-KJ8', 'Isi 8', 36000, ['DEMO-TEPUNG' => 90, 'DEMO-GULA' => 24, 'DEMO-MENTEGA' => 16, 'DEMO-KEJU' => 40]],
            ]],
            'evl-air-mineral' => ['drink', 'Air Mineral 600 ml', 'Air mineral dingin kemasan botol.', false, true, ['bg' => 'A8D8EA', 'top' => '2E86AB'], 'drink', [
                ['EVL-AIR-600', '1 botol', 5000, 'EVL-AIR'],
            ]],
            'evl-martabak-durian' => ['sweet', 'Martabak Durian (musiman)', 'Menu musiman, sementara disembunyikan dari katalog.', false, false, ['bg' => 'C8E6A0', 'top' => '9BBF3A'], 'big', [
                ['EVL-MM-DR', 'Regular', 55000, $base + ['DEMO-KEJU' => 30]],
            ]],
        ];
        $variants = [];
        $productIds = [];
        foreach ($products as $slug => [$cat, $name, $desc, $featured, $active, $palette, $kind, $vs]) {
            $productId = $productIds[$slug] = $this->upsert('products', ['slug' => $slug], [
                'category_id' => $cats[$cat], 'name' => $name, 'description' => $desc, 'product_type' => $kind === 'drink' ? 'stocked' : 'prepared',
                'is_featured' => $featured, 'is_active' => $active,
            ]);
            foreach ($vs as $i => [$sku, $vname, $price, $recipe]) {
                $direct = $kind === 'drink';
                $variantId = $variants[$sku] = $this->upsert('product_variants', ['sku' => $sku], [
                    'product_id' => $productId, 'name' => $vname, 'price' => $price, 'stock_mode' => $direct ? 'direct' : 'recipe',
                    'direct_inventory_item_id' => $direct ? $items[$recipe] : null, 'is_default' => $i === 0, 'is_active' => true,
                ]);
                if (!$direct) {
                    $recipeId = $this->upsert('recipes', ['product_variant_id' => $variantId, 'version' => 1], ['is_active' => true]);
                    foreach ($recipe as $itemSku => $quantity) {
                        $this->upsert('recipe_items', ['recipe_id' => $recipeId, 'inventory_item_id' => $items[$itemSku]], ['quantity' => $quantity]);
                    }
                }
            }
            if ($slug !== 'evl-martabak-durian') {
                $this->productImage($productId, $slug, $name, $palette, $kind);
            }
        }
        // gambar untuk produk demo bawaan
        foreach ([
            'demo-martabak-manis-original' => ['Martabak Manis Original', ['bg' => 'F9D976', 'top' => 'B8860B'], 'big'],
            'demo-martabak-manis-cokelat-keju' => ['Martabak Cokelat Keju', ['bg' => 'E8B4A0', 'top' => '4B2E1E'], 'big'],
            'demo-martabak-telur-spesial' => ['Martabak Telur Spesial', ['bg' => 'F4A261', 'top' => 'C97B2A'], 'big'],
        ] as $slug => [$name, $palette, $kind]) {
            $productId = (int) $this->db->table('products')->where('slug', $slug)->get()->getRow('id');
            if ($productId) {
                $this->productImage($productId, $slug, $name, $palette, $kind);
            }
        }

        $toppings = [];
        foreach ([['EVL-TOP-SUSU', 'Ekstra susu', 5000, 'EVL-SUSU', 30], ['EVL-TOP-KACANG', 'Kacang cincang', 4000, 'EVL-KACANG', 20]] as [$sku, $name, $price, $itemSku, $qty]) {
            $toppings[$sku] = $this->upsert('toppings', ['sku' => $sku], ['name' => $name, 'additional_price' => $price, 'is_active' => true]);
            $this->upsert('topping_recipe_items', ['topping_id' => $toppings[$sku], 'inventory_item_id' => $items[$itemSku]], ['quantity' => $qty]);
        }
        foreach (['DEMO-TOP-KEJU', 'DEMO-TOP-COKELAT'] as $sku) {
            $toppings[$sku] = (int) $this->db->table('toppings')->where('sku', $sku)->get()->getRow('id');
        }

        $this->banners();

        return ['items' => $items, 'variants' => $variants, 'toppings' => $toppings, 'suppliers' => $suppliers];
    }

    private function banners(): void
    {
        $rows = [
            ['evl-banner-akhir-pekan', 'Promo akhir pekan', 'Banner promo akhir pekan Marwis Sweet dengan ilustrasi martabak', '#menu', 1, null, null, true, ['F9D976', 'B93824'], 'Martabak hangat, harga bersahabat', 'Pesan untuk keluarga di akhir pekan'],
            ['evl-banner-mini', 'Menu mini baru', 'Banner menu martabak mini baru', '/menu/evl-mini-cokelat', 2, null, null, true, ['E8B4A0', '2D1B12'], 'Mini Martabak, sekali suap', 'Isi 4 mulai Rp18.000'],
            ['evl-banner-terjadwal', 'Promo bulan depan (terjadwal)', 'Banner promo terjadwal untuk bulan depan', null, 3, date('Y-m-d H:i:s', strtotime('+20 days')), date('Y-m-d H:i:s', strtotime('+40 days')), true, ['A8D8EA', '2E86AB'], 'Segera hadir', 'Banner ini belum tayang (terjadwal)'],
        ];
        foreach ($rows as [$slug, $title, $alt, $link, $order, $start, $end, $active, $colors, $headline, $sub]) {
            $path = 'uploads/demo/banners/' . $slug . '.jpg';
            $this->drawBanner(FCPATH . $path, $colors, $headline, $sub);
            $this->upsert('banners', ['title' => $title], ['image_path' => $path, 'alt_text' => $alt, 'link_url' => $link, 'sort_order' => $order, 'starts_at' => $start, 'ends_at' => $end, 'is_active' => $active]);
        }
    }

    // ------------------------------------------------------------------ stok

    private function postedReceipt(array $ids): void
    {
        if ($this->db->table('goods_receipts')->where('reference_number', 'EVL-PB-001')->countAllResults() > 0) {
            return;
        }
        $when = date('Y-m-d 10:00:00', strtotime('-' . (self::DAYS + 1) . ' days'));
        $lines = [
            'DEMO-TEPUNG' => [70000, 14], 'DEMO-GULA' => [30000, 18], 'DEMO-MENTEGA' => [22000, 45], 'DEMO-KEJU' => [16000, 95], 'DEMO-COKELAT' => [14000, 55],
            'DEMO-TELUR' => [700, 2200], 'EVL-SUSU' => [10000, 28], 'EVL-KACANG' => [8000, 40], 'EVL-WIJEN' => [3000, 60], 'EVL-DAGING' => [9000, 120], 'EVL-AIR' => [150, 3000],
        ];
        $this->db->transStart();
        $this->db->table('goods_receipts')->insert([
            'reference_number' => 'EVL-PB-001', 'supplier_id' => $ids['suppliers']['kue'], 'document_date' => substr($when, 0, 10), 'payment_source' => 'cash', 'status' => 'posted',
            'notes' => 'Stok awal data contoh', 'created_by' => 1, 'posted_by' => 1, 'posted_at' => $when, 'created_at' => $when, 'updated_at' => $when,
        ]);
        $receiptId = (int) $this->db->insertID();
        $totalCents = 0;
        foreach ($lines as $sku => [$qty, $cost]) {
            $itemId = $ids['items'][$sku];
            $item = $this->db->table('inventory_items')->where('id', $itemId)->get()->getRowArray();
            $lineTotalCents = Money::centsForQuantity($qty * 1000, $cost * 10000);
            $totalCents += $lineTotalCents;
            $oldQuantityMilli = Money::quantityMilli($item['quantity_on_hand']);
            $newQuantityMilli = $oldQuantityMilli + ($qty * 1000);
            $newValueCents = Money::cents($item['inventory_value']) + $lineTotalCents;
            $newAverage = Money::averageUnitCost($newValueCents, $newQuantityMilli);
            $this->db->table('inventory_items')->where('id', $itemId)->update([
                'quantity_on_hand' => Money::quantity($newQuantityMilli),
                'average_cost' => $newAverage,
                'inventory_value' => Money::decimal($newValueCents),
                'updated_at' => $when,
            ]);
            $this->db->table('goods_receipt_items')->insert([
                'goods_receipt_id' => $receiptId, 'inventory_item_id' => $itemId, 'purchase_quantity' => $qty, 'purchase_unit' => $item['base_unit'], 'factor_to_base' => 1,
                'base_quantity' => $qty, 'purchase_unit_cost' => $cost, 'base_unit_cost' => $cost, 'line_total' => Money::decimal($lineTotalCents), 'created_at' => $when, 'updated_at' => $when,
            ]);
            $this->db->table('stock_movements')->insert([
                'inventory_item_id' => $itemId, 'quantity' => $qty, 'unit_cost' => $cost, 'total_cost' => Money::decimal($lineTotalCents), 'movement_type' => 'receipt', 'source_type' => 'goods_receipt',
                'source_id' => $receiptId, 'notes' => 'EVL-PB-001', 'posted_at' => $when, 'created_by' => 1, 'created_at' => $when,
            ]);
        }
        $journalId = (new AccountingService($this->db))->post('goods_receipt', $receiptId, substr($when, 0, 10), 'Penerimaan stok EVL-PB-001', [
            ['account_key' => 'inventory', 'debit' => Money::decimal($totalCents), 'credit' => 0], ['account_key' => 'main_cash', 'debit' => 0, 'credit' => Money::decimal($totalCents)],
        ], 1);
        $this->db->table('journal_entries')->where('id', $journalId)->update(['created_at' => $when]);
        $this->db->transComplete();
    }

    private function draftReceipts(array $ids): void
    {
        foreach ([
            ['EVL-PB-DRAFT-1', 'telur', 'bank', [['DEMO-TELUR', 300, 'butir', 2250]]],
            ['EVL-PB-DRAFT-2', 'kue', 'payable', [['DEMO-TEPUNG', 25, 'kg', 14500], ['DEMO-GULA', 10, 'kg', 18500]]],
        ] as [$ref, $supplier, $source, $lines]) {
            if ($this->db->table('goods_receipts')->where('reference_number', $ref)->countAllResults() > 0) {
                continue;
            }
            $now = date('Y-m-d H:i:s');
            $this->db->table('goods_receipts')->insert(['reference_number' => $ref, 'supplier_id' => $ids['suppliers'][$supplier], 'document_date' => date('Y-m-d'), 'payment_source' => $source, 'status' => 'draft', 'notes' => 'Draft data contoh untuk uji posting', 'created_by' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $receiptId = (int) $this->db->insertID();
            foreach ($lines as [$sku, $qty, $unit, $cost]) {
                $factor = $unit === 'kg' ? 1000 : 1;
                $this->db->table('goods_receipt_items')->insert([
                    'goods_receipt_id' => $receiptId, 'inventory_item_id' => $ids['items'][$sku], 'purchase_quantity' => $qty, 'purchase_unit' => $unit, 'factor_to_base' => $factor,
                    'base_quantity' => $qty * $factor, 'purchase_unit_cost' => $cost, 'base_unit_cost' => $cost / $factor, 'line_total' => $qty * $cost, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    // ------------------------------------------------------------------ penjualan

    private function sales(): void
    {
        $cashier = $this->db->table('users')->where('role', 'cashier')->orderBy('id')->get()->getRowArray();
        if (!$cashier) {
            echo "Lewati penjualan: akun kasir tidak ditemukan.\n";

            return;
        }
        $foreignOpen = $this->db->table('shifts')->where('cashier_id', $cashier['id'])->where('status', 'open')->notLike('shift_number', 'EVL-', 'after')->countAllResults();
        if ($foreignOpen > 0) {
            echo "Lewati penjualan: tutup shift kasir yang masih terbuka terlebih dahulu.\n";

            return;
        }

        $variants = $this->db->table('product_variants')->select('product_variants.*')->join('products', 'products.id = product_variants.product_id')
            ->where('products.is_active', true)->where('product_variants.is_active', true)->get()->getResultArray();
        $toppings = $this->db->table('toppings')->where('is_active', true)->get()->getResultArray();
        $service = new SaleService($this->db);

        for ($offset = self::DAYS; $offset >= 0; $offset--) {
            $day = date('Y-m-d', strtotime("-{$offset} days"));
            $isToday = $offset === 0;
            $weekend = in_array((int) date('N', strtotime($day)), [6, 7], true);
            $count = $isToday ? 6 : ($weekend ? mt_rand(14, 20) : mt_rand(8, 13));
            $openedAt = $day . ' 15:30:00';
            $shiftNumber = 'EVL-SHIFT-' . str_replace('-', '', $day);
            $shift = $this->db->table('shifts')->where('shift_number', $shiftNumber)->get()->getRowArray();
            if ($shift && $shift['status'] === 'closed') {
                continue;
            }
            if ($shift) {
                $shiftId = (int) $shift['id'];
            } else {
                $this->db->table('shifts')->insert(['shift_number' => $shiftNumber, 'cashier_id' => $cashier['id'], 'opened_at' => $openedAt, 'opening_cash' => 200000, 'status' => 'open', 'created_at' => $openedAt, 'updated_at' => $openedAt]);
                $shiftId = (int) $this->db->insertID();
            }

            for ($n = 0; $n < $count; $n++) {
                $cart = [];
                $subtotal = 0.0;
                foreach ((array) array_rand($variants, mt_rand(1, 3)) as $index) {
                    $variant = $variants[$index];
                    $qty = mt_rand(1, 3);
                    $chosen = [];
                    $extra = 0.0;
                    if ($variant['stock_mode'] === 'recipe' && mt_rand(1, 100) <= 30) {
                        $topping = $toppings[array_rand($toppings)];
                        $chosen = [(int) $topping['id']];
                        $extra = (float) $topping['additional_price'];
                    }
                    $cart[] = ['variant_id' => (int) $variant['id'], 'quantity' => $qty, 'topping_ids' => $chosen];
                    $subtotal += ((float) $variant['price'] + $extra) * $qty;
                }
                $discount = mt_rand(1, 100) <= 10 ? 5000.0 : 0.0;
                $total = $subtotal - $discount;
                $roll = mt_rand(1, 100);
                if ($roll <= 55) {
                    $payments = [['method' => 'cash', 'amount' => $total, 'cash_received' => ceil($total / 10000) * 10000]];
                } elseif ($roll <= 85) {
                    $payments = [['method' => 'qris', 'amount' => $total, 'reference_number' => 'QRIS' . mt_rand(100000, 999999)]];
                } elseif ($roll <= 93) {
                    $payments = [['method' => 'bank_transfer', 'amount' => $total, 'reference_number' => 'TRF' . mt_rand(100000, 999999)]];
                } else {
                    $half = floor($total / 2 / 1000) * 1000;
                    $payments = [['method' => 'cash', 'amount' => $half, 'cash_received' => $half], ['method' => 'qris', 'amount' => $total - $half, 'reference_number' => 'QRIS' . mt_rand(100000, 999999)]];
                }
                try {
                    $saleId = $service->finalize(['idempotency_key' => md5("eval-sale-{$offset}-{$n}"), 'cart' => $cart, 'payments' => $payments, 'discount' => $discount], (int) $cashier['id']);
                } catch (\Throwable $exception) {
                    echo "Lewati satu transaksi ({$day}): " . $exception->getMessage() . "\n";
                    continue;
                }
                $time = $isToday
                    ? date('Y-m-d H:i:s', max(strtotime($day . ' 00:30:00'), time() - mt_rand(600, 14000)))
                    : sprintf('%s %02d:%02d:%02d', $day, mt_rand(16, 22), mt_rand(0, 59), mt_rand(0, 59));
                $this->backdate($saleId, $time, $day);
            }

            if (!$isToday) {
                $closedAt = $day . ' 23:10:00';
                $cashTotal = (float) ($this->db->table('payments')->selectSum('payments.amount', 'cash')->join('sales', 'sales.id = payments.sale_id')->where('sales.shift_id', $shiftId)->where('payments.method', 'cash')->get()->getRow('cash') ?? 0);
                $expected = 200000 + $cashTotal;
                $diff = in_array($offset, [3, 9], true) ? -5000 : 0;
                $this->db->table('shifts')->where('id', $shiftId)->update([
                    'status' => 'closed', 'closed_at' => $closedAt, 'expected_cash' => $expected, 'actual_cash' => $expected + $diff, 'cash_difference' => $diff,
                    'difference_reason' => $diff !== 0 ? 'Selisih kembalian (data contoh)' : null,
                    'cash_review_status' => $diff !== 0 ? 'pending' : 'not_required', 'updated_at' => $closedAt,
                ]);
            }
        }
    }

    private function backdate(int $saleId, string $time, string $day): void
    {
        $sale = $this->db->table('sales')->where('id', $saleId)->get()->getRowArray();
        $number = str_replace(date('Ymd'), str_replace('-', '', $day), (string) $sale['sale_number']);
        $this->db->table('sales')->where('id', $saleId)->update(['sale_number' => $number, 'paid_at' => $time, 'created_at' => $time, 'updated_at' => $time]);
        $this->db->table('sale_items')->where('sale_id', $saleId)->update(['created_at' => $time]);
        $this->db->table('payments')->where('sale_id', $saleId)->update(['created_at' => $time]);
        $this->db->table('stock_movements')->where('source_type', 'sale')->where('source_id', $saleId)->update(['posted_at' => $time, 'created_at' => $time, 'notes' => $number]);
        $this->db->table('journal_entries')->where('source_key', 'sale:' . $saleId)->update(['entry_date' => $day, 'created_at' => $time]);
    }

    // ------------------------------------------------------------------ pengeluaran & ulasan

    private function expenses(): void
    {
        if ($this->db->table('expenses')->where('payee', 'Data contoh EVL')->countAllResults() > 0) {
            return;
        }
        $categories = array_column($this->db->table('expense_categories')->get()->getResultArray(), 'id', 'name');
        $service = new ExpenseService($this->db);
        $make = fn (string $desc, string $category, float $amount, string $source, int $daysAgo): int => $service->create([
            'document_date' => date('Y-m-d', strtotime("-{$daysAgo} days")), 'expense_category_id' => $categories[$category], 'description' => $desc,
            'amount' => $amount, 'payment_source' => $source, 'payee' => 'Data contoh EVL', 'evidence' => null,
        ], 2);

        $paid = $make('Gas LPG 12 kg untuk kompor', 'Gas', 190000, 'main_cash', 6);
        $service->submit($paid, 2, 'cashier');
        $service->approve($paid, 1);
        $service->pay($paid, 1);

        $approved = $make('Tagihan listrik dan air bulan ini', 'Listrik dan air', 450000, 'bank', 3);
        $service->submit($approved, 2, 'cashier');
        $service->approve($approved, 1);

        $submitted = $make('Plastik kemasan dan box martabak', 'Lain-lain', 120000, 'main_cash', 2);
        $service->submit($submitted, 2, 'cashier');

        $make('Sewa lapak bulan depan', 'Sewa', 1500000, 'bank', 0);

        $rejected = $make('Ongkos kirim bahan dari pasar', 'Transportasi', 35000, 'main_cash', 1);
        $service->submit($rejected, 2, 'cashier');
        $service->reject($rejected, 'Lampirkan bukti ongkos kirim lalu ajukan kembali.');
    }

    private function reviews(): void
    {
        $old = array_column($this->db->table('review_tokens')->select('id')->where('disabled_reason', 'EVL contoh')->get()->getResultArray(), 'id');
        if (count($old) >= 10) {
            return;
        }
        if ($old !== []) {
            $this->db->table('product_reviews')->whereIn('review_token_id', $old)->delete();
            $this->db->table('review_tokens')->whereIn('id', $old)->delete();
        }
        $rows = $this->db->table('sale_items')->select('sale_items.id, sale_items.sale_id, sale_items.product_id')->join('sales', 'sales.id = sale_items.sale_id')
            ->where('sales.status', 'paid')->orderBy('sales.id')->limit(120)->get()->getResultArray();
        shuffle($rows);
        $comments = [
            [5, 'Adonannya lembut dan toppingnya melimpah. Pasti pesan lagi.'], [5, 'Hangat saat sampai, rasanya pas tidak terlalu manis.'], [4, 'Enak, antreannya agak lama di jam ramai.'],
            [5, 'Porsi mini cocok untuk berbagi di kantor.'], [4, 'Kejunya banyak, harga sesuai porsi.'], [3, 'Rasa enak tetapi sedikit kurang hangat ketika tiba.'],
            [5, 'Martabak telurnya gurih, dagingnya terasa.'], [4, 'Cokelatnya pas, tidak bikin enek.'], [2, 'Pesanan kurang sesuai topping yang diminta.'],
            [5, 'Pelayanan ramah dan cepat.'], [1, 'Isi kurang rapi (ulasan contoh untuk uji moderasi).'], [4, 'Kacang wijennya renyah, mantap.'],
        ];
        $statuses = ['approved', 'approved', 'approved', 'approved', 'approved', 'approved', 'approved', 'pending', 'pending', 'pending', 'rejected', 'hidden'];
        $names = ['Andi', 'Sari', 'Budi', 'Dewi', 'Rina', 'Fajar', 'Maya', 'Tono', 'Lilis', 'Eko', 'Nadia', 'Hendra'];
        $now = date('Y-m-d H:i:s');
        $used = [];
        foreach ($comments as $i => [$rating, $text]) {
            $row = array_shift($rows);
            if (!$row || isset($used[$row['sale_id']])) {
                continue;
            }
            $used[$row['sale_id']] = true;
            $this->db->table('review_tokens')->insert(['sale_id' => $row['sale_id'], 'token_hash' => hash('sha256', 'eval-token-' . $row['sale_id']), 'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')), 'disabled_reason' => 'EVL contoh', 'disabled_at' => null, 'created_at' => $now]);
            $this->db->table('product_reviews')->insert([
                'review_token_id' => $this->db->insertID(), 'sale_item_id' => $row['id'], 'product_id' => $row['product_id'], 'display_name' => $names[$i], 'rating' => $rating, 'comment' => $text,
                'status' => $statuses[$i], 'moderation_reason' => $statuses[$i] === 'rejected' ? 'Ulasan contoh ditolak untuk uji moderasi' : null,
                'moderated_by' => in_array($statuses[$i], ['approved', 'rejected', 'hidden'], true) ? 1 : null, 'moderated_at' => in_array($statuses[$i], ['approved', 'rejected', 'hidden'], true) ? $now : null,
                'submitted_ip_hash' => hash('sha256', 'eval-ip-' . $i), 'sale_fully_refunded' => false, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    // ------------------------------------------------------------------ gambar (GD)

    private function productImage(int $productId, string $slug, string $name, array $palette, string $kind): void
    {
        $path = 'uploads/demo/products/' . $slug . '.jpg';
        $this->drawProduct(FCPATH . $path, $name, $palette, $kind);
        $this->upsert('product_images', ['product_id' => $productId, 'image_path' => $path], ['alt_text' => 'Ilustrasi contoh ' . $name, 'sort_order' => 0]);
        $this->db->table('products')->where('id', $productId)->update(['image_path' => $path]);
    }

    private function color($im, string $hex, int $alpha = 0)
    {
        [$r, $g, $b] = sscanf($hex, '%2x%2x%2x');

        return imagecolorallocatealpha($im, $r, $g, $b, $alpha);
    }

    private function text($im, string $text, int $size, int $x, int $y, int $color, bool $center = false, int $width = 0): void
    {
        $font = 'C:/Windows/Fonts/arialbd.ttf';
        if (is_file($font)) {
            if ($center) {
                $box = imagettfbbox($size, 0, $font, $text);
                $x += (int) (($width - ($box[2] - $box[0])) / 2);
            }
            imagettftext($im, $size, 0, $x, $y, $color, $font, $text);

            return;
        }
        imagestring($im, 5, $x, $y - 14, $text, $color);
    }

    private function drawProduct(string $file, string $name, array $p, string $kind): void
    {
        $w = 800;
        $h = 600;
        $im = imagecreatetruecolor($w, $h);
        imageantialias($im, true);
        imagefill($im, 0, 0, $this->color($im, $p['bg']));
        imagefilledellipse($im, 400, 330, 640, 400, $this->color($im, '000000', 100));
        imagefilledellipse($im, 400, 310, 640, 400, $this->color($im, 'FFF8E7'));
        imagefilledellipse($im, 400, 310, 560, 340, $this->color($im, 'F1E3C6'));
        if ($kind === 'drink') {
            imagefilledrectangle($im, 340, 120, 460, 470, $this->color($im, 'DDF1FA'));
            imagefilledrectangle($im, 340, 250, 460, 400, $this->color($im, $p['top']));
            imagefilledrectangle($im, 375, 85, 425, 120, $this->color($im, '2D1B12'));
        } else {
            $count = $kind === 'mini' ? 4 : 1;
            for ($i = 0; $i < $count; $i++) {
                $cx = $kind === 'mini' ? 255 + ($i % 2) * 290 : 400;
                $cy = $kind === 'mini' ? 235 + intdiv($i, 2) * 150 : 305;
                $rw = $kind === 'mini' ? 230 : 470;
                $rh = $kind === 'mini' ? 120 : 250;
                imagefilledellipse($im, $cx, $cy, $rw, $rh, $this->color($im, 'D9A441'));
                imagefilledellipse($im, $cx, $cy, (int) ($rw * 0.88), (int) ($rh * 0.84), $this->color($im, 'E8BE6A'));
                for ($s = 0; $s < ($kind === 'mini' ? 14 : 55); $s++) {
                    $a = mt_rand(0, 628) / 100;
                    $r = sqrt(mt_rand(0, 100) / 100) * 0.4;
                    imagefilledellipse($im, (int) ($cx + cos($a) * $r * $rw), (int) ($cy + sin($a) * $r * $rh), mt_rand(8, 16), mt_rand(8, 14), $this->color($im, $p['top']));
                }
                imagesetthickness($im, 3);
                imageline($im, (int) ($cx - $rw / 2 + 8), $cy, (int) ($cx + $rw / 2 - 8), $cy, $this->color($im, 'B07F2A'));
            }
        }
        imagefilledrectangle($im, 0, 490, $w, $h, $this->color($im, '2D1B12', 10));
        $this->text($im, $name, 28, 0, 548, $this->color($im, 'FFF8E7'), true, $w);
        imagefilledrectangle($im, 16, 16, 170, 46, $this->color($im, '2D1B12', 20));
        $this->text($im, 'Gambar contoh', 13, 28, 37, $this->color($im, 'FACC15'));
        $this->save($im, $file);
    }

    private function drawBanner(string $file, array $colors, string $headline, string $sub): void
    {
        $w = 1600;
        $h = 500;
        $im = imagecreatetruecolor($w, $h);
        imageantialias($im, true);
        imagefill($im, 0, 0, $this->color($im, $colors[0]));
        imagefilledpolygon($im, [900, 0, 1600, 0, 1600, 500, 1200, 500], $this->color($im, $colors[1], 10));
        imagefilledellipse($im, 1400, 250, 340, 340, $this->color($im, 'FFF8E7'));
        imagefilledellipse($im, 1400, 250, 285, 285, $this->color($im, 'E8BE6A'));
        for ($i = 0; $i < 40; $i++) {
            imagefilledellipse($im, mt_rand(1290, 1510), mt_rand(140, 360), 14, 12, $this->color($im, '4B2E1E'));
        }
        $this->text($im, $headline, 42, 80, 230, $this->color($im, '2D1B12'));
        $this->text($im, $sub, 24, 84, 295, $this->color($im, '2D1B12'));
        imagefilledrectangle($im, 80, 400, 250, 440, $this->color($im, '2D1B12', 20));
        $this->text($im, 'Gambar contoh', 15, 96, 427, $this->color($im, 'FACC15'));
        $this->save($im, $file);
    }

    private function save($im, string $file): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        imagejpeg($im, $file, 84);
        imagedestroy($im);
    }

    // ------------------------------------------------------------------ util

    /**
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $values   selalu diperbarui
     * @param array<string, mixed> $initial  hanya diisi saat baris baru dibuat
     */
    private function upsert(string $table, array $identity, array $values, array $initial = []): int
    {
        $existing = $this->db->table($table)->where($identity)->get()->getRowArray();
        $now = date('Y-m-d H:i:s');
        if ($values !== [] && $this->db->fieldExists('updated_at', $table)) {
            $values['updated_at'] = $now;
        }
        if ($existing !== null) {
            if ($values !== []) {
                $this->db->table($table)->where($identity)->update($values);
            }

            return (int) $existing['id'];
        }
        $payload = array_merge($identity, $initial, $values);
        foreach (['created_at', 'updated_at'] as $column) {
            if ($this->db->fieldExists($column, $table)) {
                $payload[$column] = $now;
            }
        }
        $this->db->table($table)->insert($payload);

        return (int) $this->db->insertID();
    }
}
