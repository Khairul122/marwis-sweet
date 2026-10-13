<?php

use App\Services\ReviewService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/** @internal */
final class ReceiptMediaResponsiveWorkflowTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private $database;
    private int $ownerId;
    private int $cashierId;
    private int $categoryId;
    private int $productId;
    private int $variantId;
    private int $shiftId;
    private int $saleId;
    private string $reviewToken;
    private array $mediaPaths = [];
    private array $oldSettings = [];

    protected function setUp(): void
    {
        parent::setUp();
        $filters = config('Filters');
        $filters->globals['before'] = array_values(array_filter(
            $filters->globals['before'],
            static fn (string $filter): bool => $filter !== 'csrf',
        ));
        $this->database = db_connect();
        $suffix = strtolower(bin2hex(random_bytes(5)));
        $now = date('Y-m-d H:i:s');
        foreach ([['owner', 'owner'], ['cashier', 'cashier']] as [$name, $role]) {
            $this->database->table('users')->insert([
                'name' => 'Task 19 ' . ucfirst($name), 'username' => 't19' . $name . $suffix,
                'email' => null, 'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'role' => $role, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($role === 'owner') { $this->ownerId = (int) $this->database->insertID(); }
            else { $this->cashierId = (int) $this->database->insertID(); }
        }

        $this->database->table('categories')->insert(['name' => 'Task 19', 'slug' => 'task19-' . $suffix, 'description' => null, 'sort_order' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $this->categoryId = (int) $this->database->insertID();
        $this->database->table('products')->insert(['category_id' => $this->categoryId, 'name' => 'Produk Task 19', 'slug' => 'produk-task19-' . $suffix, 'description' => null, 'product_type' => 'prepared', 'image_path' => null, 'is_featured' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $this->productId = (int) $this->database->insertID();
        $this->database->table('product_variants')->insert(['product_id' => $this->productId, 'name' => 'Reguler', 'sku' => 'T19-' . strtoupper($suffix), 'price' => '12500.00', 'stock_mode' => 'none', 'is_default' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $this->variantId = (int) $this->database->insertID();
        $this->database->table('shifts')->insert(['shift_number' => 'T19-' . strtoupper($suffix), 'cashier_id' => $this->cashierId, 'opened_at' => $now, 'opening_cash' => '50000.00', 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]);
        $this->shiftId = (int) $this->database->insertID();
        $this->database->table('sales')->insert([
            'sale_number' => 'T19-' . strtoupper($suffix), 'idempotency_key' => hash('sha256', $suffix),
            'shift_id' => $this->shiftId, 'cashier_id' => $this->cashierId, 'status' => 'paid',
            'subtotal' => '12500.00', 'discount' => '0.00', 'tax' => '0.00', 'service_charge' => '0.00',
            'total' => '12500.00', 'total_cogs' => '0.00', 'paid_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->saleId = (int) $this->database->insertID();
        $this->database->table('sale_items')->insert([
            'sale_id' => $this->saleId, 'product_id' => $this->productId, 'product_variant_id' => $this->variantId,
            'product_name' => 'Produk Task 19', 'variant_name' => 'Reguler', 'sku' => 'T19-' . strtoupper($suffix),
            'quantity' => '1.000', 'unit_price' => '12500.00', 'line_total' => '12500.00', 'unit_cogs' => '0.0000',
            'line_cogs' => '0.00', 'recipe_snapshot' => null, 'topping_snapshot' => '[]', 'created_at' => $now,
        ]);
        $this->database->table('payments')->insert(['sale_id' => $this->saleId, 'method' => 'cash', 'amount' => '12500.00', 'cash_received' => '15000.00', 'change_amount' => '2500.00', 'created_at' => $now]);

        foreach (['business_name', 'address', 'phone'] as $key) {
            $old = $this->database->table('settings')->where('setting_key', $key)->get()->getRowArray();
            $this->oldSettings[$key] = $old['setting_value'] ?? null;
        }
        foreach (['business_name' => 'Toko Verifikasi Struk', 'address' => 'Jl. Uji Coba 17, Bandung', 'phone' => '081234567890'] as $key => $value) {
            $this->database->table('settings')->replace(['setting_key' => $key, 'setting_value' => $value, 'updated_at' => $now]);
        }
        $this->reviewToken = (new ReviewService($this->database))->issueToken($this->saleId) ?? '';
    }

    protected function tearDown(): void
    {
        if (isset($this->database, $this->saleId)) {
            $this->database->table('review_tokens')->where('sale_id', $this->saleId)->delete();
            $this->database->table('payments')->where('sale_id', $this->saleId)->delete();
            $this->database->table('sale_items')->where('sale_id', $this->saleId)->delete();
            $this->database->table('sales')->where('id', $this->saleId)->delete();
            $this->database->table('shifts')->where('id', $this->shiftId)->delete();
            $banners = $this->database->table('banners')->select('image_path')->like('title', 'Task 20 banner %')->get()->getResultArray();
            $this->database->table('banners')->like('title', 'Task 20 banner %')->delete();
            foreach ($banners as $banner) { $this->mediaPaths[] = $banner['image_path']; }
            $images = $this->database->table('product_images')->select('image_path')->where('product_id', $this->productId)->get()->getResultArray();
            $this->database->table('product_images')->where('product_id', $this->productId)->delete();
            foreach ($images as $image) { $this->mediaPaths[] = $image['image_path']; }
            $this->database->table('products')->where('id', $this->productId)->delete();
            $this->database->table('categories')->where('id', $this->categoryId)->delete();
            $this->database->table('users')->whereIn('id', [$this->ownerId, $this->cashierId])->delete();
            foreach ($this->oldSettings as $key => $value) {
                if ($value === null) { $this->database->table('settings')->where('setting_key', $key)->delete(); }
                else { $this->database->table('settings')->replace(['setting_key' => $key, 'setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')]); }
            }
        }
        foreach (array_unique($this->mediaPaths) as $relativePath) {
            $path = realpath(FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
            $uploadsRoot = realpath(FCPATH . 'uploads');
            if ($uploadsRoot && $path && str_starts_with($path, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($path)) { @unlink($path); }
        }
        service('superglobals')->setFilesArray([]);
        parent::tearDown();
    }

    public function testReceiptUsesSavedIdentityReviewLinkAndPrintOptions(): void
    {
        $receipt = $this->withSession(['user_id' => $this->cashierId])->get('panel/transactions/' . $this->saleId);
        $receipt->assertOK();
        $receipt->assertSee('Toko Verifikasi Struk');
        $receipt->assertSee('Jl. Uji Coba 17, Bandung');
        $receipt->assertSee('081234567890');
        $receipt->assertSee('value="80"');
        $receipt->assertSee('value="58"');
        $receipt->assertSee(site_url('ulasan/' . $this->reviewToken));
        $receipt->assertSee('Unduh PDF');

        $pdf = $this->withSession(['user_id' => $this->ownerId])->get('panel/transactions/' . $this->saleId . '.pdf');
        $pdf->assertOK();
        self::assertSame('application/pdf', $pdf->response()->getHeaderLine('Content-Type'));
        self::assertStringContainsString('%PDF-', $pdf->getBody());
    }

    public function testProductAndBannerMediaCanBeMaintainedWithAccessiblePreviews(): void
    {
        $now = date('Y-m-d H:i:s');
        $firstPath = 'uploads/products/task20-first-' . bin2hex(random_bytes(4)) . '.png';
        $secondPath = 'uploads/products/task20-second-' . bin2hex(random_bytes(4)) . '.png';
        foreach ([[$firstPath, 'Foto produk Task 20 1'], [$secondPath, 'Foto produk Task 20 2']] as [$path, $alt]) {
            $this->database->table('product_images')->insert([
                'product_id' => $this->productId, 'image_path' => $path, 'alt_text' => $alt,
                'sort_order' => $path === $firstPath ? 0 : 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $this->database->table('products')->where('id', $this->productId)->update(['image_path' => $firstPath]);
        $images = $this->database->table('product_images')->where('product_id', $this->productId)->orderBy('sort_order')->get()->getResultArray();
        self::assertCount(2, $images);
        self::assertSame($images[0]['image_path'], $this->database->table('products')->where('id', $this->productId)->get()->getRow('image_path'));

        $this->withSession(['user_id' => $this->ownerId])->post('panel/catalog/products/images/order', ['image_id' => $images[1]['id'], 'direction' => 'up'])->assertRedirect();
        self::assertSame((int) $images[1]['id'], (int) $this->database->table('product_images')->where('product_id', $this->productId)->orderBy('sort_order')->get()->getRow('id'));
        $this->withSession(['user_id' => $this->ownerId])->post('panel/catalog/products/images/update', ['image_id' => $images[1]['id'], 'alt_text' => 'Foto produk utama diperbarui'])->assertRedirect();
        self::assertSame('Foto produk utama diperbarui', $this->database->table('product_images')->where('id', $images[1]['id'])->get()->getRow('alt_text'));

        $productEdit = $this->withSession(['user_id' => $this->ownerId])->get('panel/data/product/' . $this->productId . '/edit');
        $productEdit->assertOK();
        $productEdit->assertSee('Naikkan');
        $productEdit->assertSee('Turunkan');
        $productEdit->assertSee('Hapus foto');
        $productEdit->assertSee('Ganti gambar');

        $bannerCreate = $this->withSession(['user_id' => $this->ownerId])->get('panel/data/banner/create');
        $bannerCreate->assertOK();
        $bannerCreate->assertSee('Desktop · 16:5');
        $bannerCreate->assertSee('Ponsel · 4:3');
        $bannerCreate->assertSee('data-image-preview-input');

        $oldBannerPath = 'uploads/banners/task20-' . bin2hex(random_bytes(5)) . '.png';
        $this->database->table('banners')->insert([
            'title' => 'Task 20 banner uji', 'image_path' => $oldBannerPath,
            'alt_text' => 'Banner uji untuk desktop dan ponsel', 'link_url' => null,
            'sort_order' => 0, 'is_active' => true, 'starts_at' => null, 'ends_at' => null,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $banner = $this->database->table('banners')->where('title', 'Task 20 banner uji')->orderBy('id', 'DESC')->get()->getRowArray();
        self::assertNotEmpty($banner);
        $this->mediaPaths[] = $oldBannerPath;
        $bannerEdit = $this->withSession(['user_id' => $this->ownerId])->get('panel/data/banner/' . $banner['id'] . '/edit');
        $bannerEdit->assertOK();
        $bannerEdit->assertSee('Ganti gambar (opsional');
        $bannerEdit->assertSee('Pratinjau banner');
        $bannerEdit->assertSee(base_url($oldBannerPath));

        $this->withSession(['user_id' => $this->ownerId])->post('panel/settings/banners/update', [
            'banner_id' => $banner['id'], 'title' => $banner['title'], 'alt_text' => $banner['alt_text'],
            'link_url' => '', 'sort_order' => '0', 'starts_at' => '', 'ends_at' => '',
        ])->assertRedirect();
        $updated = $this->database->table('banners')->where('id', $banner['id'])->get()->getRowArray();
        self::assertSame($oldBannerPath, $updated['image_path'], 'Tanpa unggahan baru, gambar banner lama harus dipertahankan.');

        $this->withSession(['user_id' => $this->ownerId])->post('panel/catalog/products/images/delete', ['image_id' => $images[1]['id']])->assertRedirect();
        $firstImage = $this->database->table('product_images')->where('product_id', $this->productId)->orderBy('sort_order')->get()->getRowArray();
        self::assertSame($firstImage['image_path'], $this->database->table('products')->where('id', $this->productId)->get()->getRow('image_path'));
    }

    public function testPanelTablesAndPosExposeResponsiveAndKeyboardStates(): void
    {
        $catalog = $this->withSession(['user_id' => $this->ownerId])->get('panel/catalog');
        $catalog->assertOK();
        $catalog->assertSee('class="panel-data-table');
        $catalog->assertSee('data-label="Nama produk"');
        $catalog->assertSee('data-label="Harga"');

        $pos = $this->withSession(['user_id' => $this->cashierId])->get('panel/pos');
        $pos->assertOK();
        $pos->assertSee('max-md:translate-y-full');
        $pos->assertSee('data-pos-cart');
        $pos->assertSee('aria-hidden="true" inert');
        $pos->assertDontSee('data-drawer');
        $panel = $this->withSession(['user_id' => $this->ownerId])->get('panel/catalog');
        $panel->assertOK();
        $panel->assertSee('data-drawer');
        $panel->assertSee('aria-hidden="true" inert');
        $script = file_get_contents(FCPATH . 'assets/js/app.js');
        self::assertStringContainsString('drawerFocusables', $script);
        self::assertStringContainsString('cartFocusables', $script);
        self::assertStringContainsString("event.key === 'Escape'", $script);
    }

}
