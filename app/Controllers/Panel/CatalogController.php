<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Services\ImageUploadService;
use App\Services\AuditLogService;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

class CatalogController extends BaseController
{
    public function index(): string
    {
        $categories = (new CategoryModel())->orderBy('sort_order')->orderBy('name')->findAll();
        $products = (new ProductModel())
            ->select('products.*, categories.name AS category_name')
            ->join('categories', 'categories.id = products.category_id')
            ->orderBy('products.name')
            ->findAll();
        $variants = (new ProductVariantModel())->orderBy('is_default', 'DESC')->orderBy('price')->findAll();
        $variantsByProduct = [];
        foreach ($variants as $variant) {
            $variantsByProduct[$variant['product_id']][] = $variant;
        }
        $imagesByProduct = [];
        foreach (db_connect()->table('product_images')->orderBy('sort_order')->get()->getResultArray() as $image) {
            $imagesByProduct[$image['product_id']][] = $image;
        }

        return view('panel/catalog/index', [
            'title' => 'Produk dan kategori',
            'user' => session('user'),
            'categories' => $categories,
            'products' => $products,
            'variantsByProduct' => $variantsByProduct,
            'imagesByProduct' => $imagesByProduct,
            'inventoryItems' => db_connect()->table('inventory_items')->where('is_active', true)->orderBy('name')->get()->getResultArray(),
        ]);
    }

    public function createCategory(): RedirectResponse
    {
        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'description' => 'permit_empty|max_length[255]',
            'sort_order' => 'permit_empty|is_natural|max_length[5]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('categoryErrors', $this->validator->getErrors());
        }

        $name = trim((string) $this->request->getPost('name'));
        $slug = $this->uniqueSlug('categories', $name);
        (new CategoryModel())->insert([
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'sort_order' => (int) ($this->request->getPost('sort_order') ?: 0),
            'is_active' => true,
        ]);

        return redirect()->route('panel.catalog')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function toggleCategory(): RedirectResponse
    {
        $category = $this->findCategory();
        if (!$category) {
            return redirect()->route('panel.catalog')->with('error', 'Kategori tidak ditemukan.');
        }

        (new CategoryModel())->update($category['id'], ['is_active' => !$category['is_active']]);
        return redirect()->route('panel.catalog')->with('success', $category['is_active'] ? 'Kategori dinonaktifkan.' : 'Kategori diaktifkan.');
    }

    public function updateCategory(): RedirectResponse
    {
        $category = $this->findCategory();
        if (!$category) {
            return redirect()->route('panel.catalog')->with('error', 'Kategori tidak ditemukan.');
        }
        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'description' => 'permit_empty|max_length[255]',
            'sort_order' => 'required|is_natural|max_length[5]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        $name = trim((string) $this->request->getPost('name'));
        $slug = $category['name'] === $name ? $category['slug'] : $this->uniqueSlug('categories', $name);
        (new CategoryModel())->update($category['id'], [
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'sort_order' => (int) $this->request->getPost('sort_order'),
        ]);
        return redirect()->route('panel.catalog')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function createProduct(): RedirectResponse
    {
        $rules = [
            'category_id' => 'required|is_natural_no_zero',
            'name' => 'required|min_length[2]|max_length[140]',
            'description' => 'permit_empty|max_length[1000]',
            'product_type' => 'required|in_list[prepared,stocked]',
            'variant_name' => 'required|min_length[1]|max_length[100]',
            'sku' => 'required|alpha_numeric_punct|min_length[2]|max_length[80]|is_unique[product_variants.sku]',
            'price' => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[9999999999999]',
            'stock_mode' => 'required|in_list[recipe,direct,none]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('productErrors', $this->validator->getErrors());
        }

        $categoryId = (int) $this->request->getPost('category_id');
        if (!(new CategoryModel())->find($categoryId)) {
            return redirect()->back()->withInput()->with('error', 'Kategori yang dipilih tidak ditemukan.');
        }

        $database = db_connect();
        $stockMode = (string) $this->request->getPost('stock_mode');
        $directItemId = $this->directInventoryItemId($stockMode);
        if ($stockMode === 'direct' && !$directItemId) {
            return redirect()->back()->withInput()->with('error', 'Pilih item persediaan untuk varian dengan stok langsung.');
        }
        $database->transStart();
        $productModel = new ProductModel();
        $name = trim((string) $this->request->getPost('name'));
        $productId = $productModel->insert([
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $this->uniqueSlug('products', $name),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'product_type' => (string) $this->request->getPost('product_type'),
            'is_featured' => $this->request->getPost('is_featured') === '1',
            'is_active' => true,
        ], true);
        (new ProductVariantModel())->insert([
            'product_id' => $productId,
            'name' => trim((string) $this->request->getPost('variant_name')),
            'sku' => strtoupper(trim((string) $this->request->getPost('sku'))),
            'price' => (string) $this->request->getPost('price'),
            'stock_mode' => $stockMode,
            'direct_inventory_item_id' => $directItemId,
            'is_default' => true,
            'is_active' => true,
        ]);
        $database->transComplete();

        if (!$database->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Produk belum dapat disimpan. Coba lagi.');
        }
        return redirect()->route('panel.catalog')->with('success', 'Produk dan varian awal berhasil ditambahkan.');
    }

    public function addVariant(): RedirectResponse
    {
        $productId = filter_var($this->request->getPost('product_id'), FILTER_VALIDATE_INT);
        $product = $productId ? (new ProductModel())->find($productId) : null;
        if (!$product) {
            return redirect()->route('panel.catalog')->with('error', 'Produk tidak ditemukan.');
        }

        $rules = [
            'variant_name' => 'required|min_length[1]|max_length[100]',
            'sku' => 'required|alpha_numeric_punct|min_length[2]|max_length[80]|is_unique[product_variants.sku]',
            'price' => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[9999999999999]',
            'stock_mode' => 'required|in_list[recipe,direct,none]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $stockMode = (string) $this->request->getPost('stock_mode');
        $directItemId = $this->directInventoryItemId($stockMode);
        if ($stockMode === 'direct' && !$directItemId) {
            return redirect()->route('panel.catalog')->with('error', 'Pilih item persediaan untuk varian dengan stok langsung.');
        }
        (new ProductVariantModel())->insert([
            'product_id' => $product['id'],
            'name' => trim((string) $this->request->getPost('variant_name')),
            'sku' => strtoupper(trim((string) $this->request->getPost('sku'))),
            'price' => (string) $this->request->getPost('price'),
            'stock_mode' => $stockMode,
            'direct_inventory_item_id' => $directItemId,
            'is_default' => false,
            'is_active' => true,
        ]);

        return redirect()->route('panel.catalog')->with('success', 'Varian berhasil ditambahkan.');
    }

    public function updateProduct(): RedirectResponse
    {
        $productId = filter_var($this->request->getPost('product_id'), FILTER_VALIDATE_INT);
        $model = new ProductModel();
        $product = $productId ? $model->find($productId) : null;
        if (!$product) {
            return redirect()->route('panel.catalog')->with('error', 'Produk tidak ditemukan.');
        }
        $rules = [
            'category_id' => 'required|is_natural_no_zero',
            'name' => 'required|min_length[2]|max_length[140]',
            'description' => 'permit_empty|max_length[1000]',
            'product_type' => 'required|in_list[prepared,stocked]',
        ];
        if (!$this->validate($rules) || !(new CategoryModel())->find((int) $this->request->getPost('category_id'))) {
            return redirect()->route('panel.catalog')->with('error', $this->validator ? implode(' ', $this->validator->getErrors()) : 'Kategori tidak ditemukan.');
        }
        $name = trim((string) $this->request->getPost('name'));
        $model->update($product['id'], [
            'category_id' => (int) $this->request->getPost('category_id'),
            'name' => $name,
            'slug' => $product['name'] === $name ? $product['slug'] : $this->uniqueSlug('products', $name),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'product_type' => (string) $this->request->getPost('product_type'),
            'is_featured' => $this->request->getPost('is_featured') === '1',
        ]);
        return redirect()->route('panel.catalog')->with('success', 'Produk berhasil diperbarui.');
    }

    public function updateVariant(): RedirectResponse
    {
        $variantId = filter_var($this->request->getPost('variant_id'), FILTER_VALIDATE_INT);
        $model = new ProductVariantModel();
        $variant = $variantId ? $model->find($variantId) : null;
        if (!$variant) {
            return redirect()->route('panel.catalog')->with('error', 'Varian tidak ditemukan.');
        }
        $rules = [
            'variant_name' => 'required|min_length[1]|max_length[100]',
            'sku' => 'required|alpha_numeric_punct|min_length[2]|max_length[80]|is_unique[product_variants.sku,id,' . $variant['id'] . ']',
            'price' => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[9999999999999]',
            'stock_mode' => 'required|in_list[recipe,direct,none]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        $stockMode = (string) $this->request->getPost('stock_mode');
        $directItemId = $this->directInventoryItemId($stockMode);
        if ($stockMode === 'direct' && !$directItemId) {
            return redirect()->route('panel.catalog')->with('error', 'Pilih item persediaan untuk varian dengan stok langsung.');
        }
        $data = [
            'name' => trim((string) $this->request->getPost('variant_name')),
            'sku' => strtoupper(trim((string) $this->request->getPost('sku'))),
            'price' => (string) $this->request->getPost('price'),
            'stock_mode' => $stockMode,
            'direct_inventory_item_id' => $directItemId,
            'is_active' => $this->request->getPost('is_active') === '1',
        ];
        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            $model->update($variant['id'], $data);
            (new AuditLogService($database))->record((int) session('user_id'), 'catalog.variant.updated', 'product_variant', $variant['id'], 'Detail atau harga varian diperbarui.', [
                'name' => $variant['name'], 'sku' => $variant['sku'], 'price' => $variant['price'],
                'stock_mode' => $variant['stock_mode'], 'direct_inventory_item_id' => $variant['direct_inventory_item_id'], 'is_active' => (bool) $variant['is_active'],
            ], [
                'name' => $data['name'], 'sku' => $data['sku'], 'price' => $data['price'],
                'stock_mode' => $data['stock_mode'], 'direct_inventory_item_id' => $data['direct_inventory_item_id'], 'is_active' => $data['is_active'],
            ]);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->route('panel.catalog')->with('error', 'Perubahan varian belum tersimpan: ' . $exception->getMessage());
        }
        return redirect()->route('panel.catalog')->with('success', 'Varian berhasil diperbarui.');
    }

    public function toggleProduct(): RedirectResponse
    {
        $productId = filter_var($this->request->getPost('product_id'), FILTER_VALIDATE_INT);
        $productModel = new ProductModel();
        $product = $productId ? $productModel->find($productId) : null;
        if (!$product) {
            return redirect()->route('panel.catalog')->with('error', 'Produk tidak ditemukan.');
        }

        $productModel->update($product['id'], ['is_active' => !$product['is_active']]);
        return redirect()->route('panel.catalog')->with('success', $product['is_active'] ? 'Produk disembunyikan dari katalog.' : 'Produk ditampilkan di katalog.');
    }

    public function addImage(): RedirectResponse
    {
        $productId = filter_var($this->request->getPost('product_id'), FILTER_VALIDATE_INT);
        $productModel = new ProductModel();
        $product = $productId ? $productModel->find($productId) : null;
        if (!$product) {
            return redirect()->route('panel.catalog')->with('error', 'Produk tidak ditemukan.');
        }
        $altText = trim((string) $this->request->getPost('alt_text'));
        if (mb_strlen($altText) < 5 || mb_strlen($altText) > 180) {
            return redirect()->back()->withInput()->with('error', 'Teks alternatif foto harus berisi 5 sampai 180 karakter.');
        }
        $database = db_connect();
        $file = $this->request->getFile('image');
        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return redirect()->back()->withInput()->with('error', 'Pilih gambar produk.');
        }
        try {
            $imagePath = (new ImageUploadService())->store($file, 'products', 2 * 1024 * 1024);
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
        try {
            $database->transException(true)->transStart();
            $lockedProduct = $database->query('SELECT id, image_path FROM products WHERE id = ? FOR UPDATE', [$product['id']])->getRowArray();
            $imageCount = $database->table('product_images')->where('product_id', $product['id'])->countAllResults();
            if (!$lockedProduct || $imageCount >= 5) { throw new RuntimeException('Satu produk dapat memiliki maksimal 5 foto.'); }
            $sortOrder = (int) ($database->table('product_images')->selectMax('sort_order', 'sort_order')->where('product_id', $product['id'])->get()->getRow('sort_order') ?? -1) + 1;
            $now = date('Y-m-d H:i:s');
            $database->table('product_images')->insert([
                'product_id' => $product['id'], 'image_path' => $imagePath, 'alt_text' => $altText,
                'sort_order' => $sortOrder, 'created_at' => $now, 'updated_at' => $now,
            ]);
            if (!$lockedProduct['image_path']) { $productModel->update($product['id'], ['image_path' => $imagePath]); }
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            $this->deleteMediaIfUnused($imagePath);
            return redirect()->to(site_url('panel/data/product/' . $product['id'] . '/edit'))->with('error', $exception->getMessage());
        }
        return redirect()->to(site_url('panel/data/product/' . $product['id'] . '/edit'))->with('success', 'Foto produk berhasil ditambahkan.');
    }

    public function updateImage(): RedirectResponse
    {
        $imageId = filter_var($this->request->getPost('image_id'), FILTER_VALIDATE_INT);
        $database = db_connect();
        $image = $imageId ? $database->table('product_images')->where('id', $imageId)->get()->getRowArray() : null;
        if (!$image) { return redirect()->route('panel.catalog')->with('error', 'Foto produk tidak ditemukan.'); }
        $altText = trim((string) $this->request->getPost('alt_text'));
        if (mb_strlen($altText) < 5 || mb_strlen($altText) > 180) {
            return redirect()->to(site_url('panel/data/product/' . $image['product_id'] . '/edit'))->withInput()->with('error', 'Teks alternatif foto harus berisi 5 sampai 180 karakter.');
        }
        $file = $this->request->getFile('image');
        $newPath = null;
        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            try { $newPath = (new ImageUploadService())->store($file, 'products', 2 * 1024 * 1024); }
            catch (RuntimeException $exception) { return redirect()->to(site_url('panel/data/product/' . $image['product_id'] . '/edit'))->withInput()->with('error', $exception->getMessage()); }
        }
        try {
            $database->transException(true)->transStart();
            $database->table('product_images')->where('id', $imageId)->update(array_filter([
                'alt_text' => $altText, 'image_path' => $newPath, 'updated_at' => date('Y-m-d H:i:s'),
            ], static fn ($value): bool => $value !== null));
            $this->syncProductPrimaryImage((int) $image['product_id']);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            if ($newPath) { $this->deleteMediaIfUnused($newPath); }
            return redirect()->to(site_url('panel/data/product/' . $image['product_id'] . '/edit'))->with('error', 'Foto produk belum berhasil diperbarui.');
        }
        if ($newPath) { $this->deleteMediaIfUnused($image['image_path']); }
        return redirect()->to(site_url('panel/data/product/' . $image['product_id'] . '/edit'))->with('success', 'Foto produk berhasil diperbarui.');
    }

    public function moveImage(): RedirectResponse
    {
        $imageId = filter_var($this->request->getPost('image_id'), FILTER_VALIDATE_INT);
        $direction = (string) $this->request->getPost('direction');
        $database = db_connect();
        $image = $imageId ? $database->table('product_images')->where('id', $imageId)->get()->getRowArray() : null;
        if (!$image || !in_array($direction, ['up', 'down'], true)) { return redirect()->route('panel.catalog')->with('error', 'Urutan foto tidak valid.'); }
        $images = $database->table('product_images')->where('product_id', $image['product_id'])->orderBy('sort_order')->orderBy('id')->get()->getResultArray();
        $position = array_search((int) $imageId, array_map(static fn (array $row): int => (int) $row['id'], $images), true);
        $target = $direction === 'up' ? $position - 1 : $position + 1;
        if ($position === false || !isset($images[$target])) { return redirect()->to(site_url('panel/data/product/' . $image['product_id'] . '/edit')); }
        $moved = $images[$position];
        array_splice($images, $position, 1);
        array_splice($images, $target, 0, [$moved]);
        try {
            $database->transException(true)->transStart();
            foreach ($images as $sortOrder => $row) {
                $database->table('product_images')->where('id', $row['id'])->update(['sort_order' => $sortOrder, 'updated_at' => date('Y-m-d H:i:s')]);
            }
            $this->syncProductPrimaryImage((int) $image['product_id']);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->to(site_url('panel/data/product/' . $image['product_id'] . '/edit'))->with('error', 'Urutan foto belum berhasil diperbarui.');
        }
        return redirect()->to(site_url('panel/data/product/' . $image['product_id'] . '/edit'))->with('success', 'Urutan foto produk diperbarui.');
    }

    public function deleteImage(): RedirectResponse
    {
        $imageId = filter_var($this->request->getPost('image_id'), FILTER_VALIDATE_INT);
        $database = db_connect();
        $image = $imageId ? $database->table('product_images')->where('id', $imageId)->get()->getRowArray() : null;
        if (!$image) { return redirect()->route('panel.catalog')->with('error', 'Foto produk tidak ditemukan.'); }
        try {
            $database->transException(true)->transStart();
            $database->table('product_images')->where('id', $imageId)->delete();
            $this->syncProductPrimaryImage((int) $image['product_id']);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->to(site_url('panel/data/product/' . $image['product_id'] . '/edit'))->with('error', 'Foto produk belum dapat dihapus.');
        }
        $this->deleteMediaIfUnused($image['image_path']);
        return redirect()->to(site_url('panel/data/product/' . $image['product_id'] . '/edit'))->with('success', 'Foto produk dihapus.');
    }

    public function deleteCategory(): RedirectResponse
    {
        $id = filter_var($this->request->getPost('category_id'), FILTER_VALIDATE_INT);
        if (!$id || !(new CategoryModel())->find($id)) {
            return redirect()->route('panel.catalog')->with('error', 'Kategori tidak ditemukan.');
        }

        if (db_connect()->table('products')->where('category_id', $id)->countAllResults() > 0) {
            return redirect()->route('panel.catalog')->with('error', 'Kategori masih dipakai oleh produk dan tidak dapat dihapus. Pindahkan atau hapus produknya lebih dulu, atau nonaktifkan kategori ini.');
        }

        return $this->deleteRow('categories', $id, 'Kategori berhasil dihapus.', 'Kategori masih dipakai oleh produk dan tidak dapat dihapus. Nonaktifkan saja bila tidak dipakai lagi.');
    }

    public function deleteProduct(): RedirectResponse
    {
        $id = filter_var($this->request->getPost('product_id'), FILTER_VALIDATE_INT);
        $product = $id ? (new ProductModel())->find($id) : null;
        if (!$product) {
            return redirect()->route('panel.catalog')->with('error', 'Produk tidak ditemukan.');
        }
        $images = db_connect()->table('product_images')->select('image_path')->where('product_id', $id)->get()->getResultArray();
        $response = $this->deleteRow('products', $id, 'Produk berhasil dihapus.', 'Produk sudah punya riwayat transaksi atau ulasan dan tidak dapat dihapus. Sembunyikan dari katalog dengan menonaktifkannya.');
        if (session()->has('success')) {
            foreach ($images as $image) { $this->deleteMediaIfUnused($image['image_path']); }
        }

        return $response;
    }

    private function deleteRow(string $table, int $id, string $success, string $blocked): RedirectResponse
    {
        try {
            db_connect()->table($table)->where('id', $id)->delete();
        } catch (\Throwable) {
            return redirect()->route('panel.catalog')->with('error', $blocked);
        }

        return redirect()->route('panel.catalog')->with('success', $success);
    }

    private function syncProductPrimaryImage(int $productId): void
    {
        $first = db_connect()->table('product_images')->where('product_id', $productId)->orderBy('sort_order')->orderBy('id')->get()->getRowArray();
        (new ProductModel())->update($productId, ['image_path' => $first['image_path'] ?? null]);
    }

    private function deleteMediaIfUnused(string $relativePath): void
    {
        $database = db_connect();
        foreach (['product_images', 'products', 'banners'] as $table) {
            if ($database->table($table)->where('image_path', $relativePath)->countAllResults() > 0) { return; }
        }
        $uploadsRoot = realpath(FCPATH . 'uploads');
        $path = realpath(FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($uploadsRoot && $path && str_starts_with($path, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($path)) { @unlink($path); }
    }

    private function findCategory(): ?array
    {
        $id = filter_var($this->request->getPost('category_id'), FILTER_VALIDATE_INT);
        return $id ? (new CategoryModel())->find($id) : null;
    }

    private function uniqueSlug(string $table, string $name): string
    {
        $asciiName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $asciiName), '-')) ?: 'item';
        $slug = $base;
        $suffix = 2;
        $builder = db_connect()->table($table);
        while ($builder->where('slug', $slug)->countAllResults() > 0) {
            $slug = $base . '-' . $suffix++;
        }
        return $slug;
    }

    private function directInventoryItemId(string $stockMode): ?int
    {
        if ($stockMode !== 'direct') {
            return null;
        }
        $itemId = filter_var($this->request->getPost('direct_inventory_item_id'), FILTER_VALIDATE_INT);
        if (!$itemId || !db_connect()->table('inventory_items')->where('id', $itemId)->where('is_active', true)->get()->getRowArray()) {
            return null;
        }
        return (int) $itemId;
    }
}
