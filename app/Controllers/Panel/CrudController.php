<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * CRUD sederhana: index (tabel), form (tambah), edit. Penyimpanan tetap lewat
 * handler POST di controller masing-masing modul.
 */
class CrudController extends BaseController
{
    private const UNITS = ['gram' => 'Gram', 'mililiter' => 'Mililiter', 'pcs' => 'Pcs'];
    private const STOCK_MODES = ['recipe' => 'Berdasarkan resep', 'direct' => 'Stok produk langsung', 'none' => 'Tanpa pelacakan stok'];

    public function index(string $entity): string
    {
        $config = $this->config($entity);
        if (!isset($config['columns'])) {
            throw PageNotFoundException::forPageNotFound();
        }
        $builder = db_connect()->table($config['table'])->select($config['table'] . '.*' . ($config['select'] ?? ''));
        foreach ($config['join'] ?? [] as $join) {
            $builder->join($join[0], $join[1], 'left');
        }
        $rows = $builder->where($config['where'] ?? [])->orderBy($config['order'] ?? ($config['table'] . '.id DESC'))->get()->getResultArray();

        return view('panel/crud/index', [
            'title' => ucfirst($config['plural']),
            'user' => session('user'),
            'entity' => $entity,
            'config' => $config,
            'rows' => $rows,
        ]);
    }

    public function create(string $entity): string
    {
        return $this->render($entity, null, 'panel/crud/form');
    }

    public function edit(string $entity, int $id): string
    {
        return $this->render($entity, $id, 'panel/crud/edit');
    }

    private function config(string $entity): array
    {
        $config = $this->entities()[$entity] ?? throw PageNotFoundException::forPageNotFound();
        if (empty($config['staff']) && (session('user')['role'] ?? '') !== 'owner') {
            throw PageNotFoundException::forPageNotFound();
        }
        $config['index'] = isset($config['columns']) ? 'panel/data/' . $entity : $config['back'];

        return $config;
    }

    private function render(string $entity, ?int $id, string $view): string
    {
        $config = $this->config($entity);
        $row = [];
        if ($id !== null) {
            if (!isset($config['update'])) {
                throw PageNotFoundException::forPageNotFound();
            }
            $row = db_connect()->table($config['table'])->where($config['where'] ?? [])->where('id', $id)->get()->getRowArray() ?? throw PageNotFoundException::forPageNotFound();
            if ($entity === 'expense' && (!in_array($row['status'], ['draft', 'rejected'], true) || (int) $row['created_by'] !== (int) session('user_id'))) {
                throw PageNotFoundException::forPageNotFound();
            }
            if (!empty($row['starts_at'])) {
                $row['starts_at'] = date('Y-m-d\TH:i', strtotime($row['starts_at']));
            }
            if (!empty($row['ends_at'])) {
                $row['ends_at'] = date('Y-m-d\TH:i', strtotime($row['ends_at']));
            }
        }
        $mode = $id === null ? 'create' : 'edit';
        $hidden = $id === null ? ($config['hiddenOnCreate'] ?? []) : [$config['idField'] => $id];
        foreach ($hidden as $name => $value) {
            if ($value === '@query') {
                $hidden[$name] = (int) $this->request->getGet($name);
            }
        }
        if ($id === null && isset($config['hiddenOnCreate']) && in_array(0, $hidden, true)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $fields = array_values(array_filter($config['fields'], static fn (array $f) => ($f['only'] ?? $mode) === $mode));
        foreach ($fields as &$field) {
            if (($field['default'] ?? null) === '@today') {
                $field['default'] = date('Y-m-d');
            }
            if (isset($field['source'])) {
                $field['options'] = $this->options($field['source']);
            }
        }
        unset($field);

        $errors = array_merge(
            session()->getFlashdata('errors') ?? [],
            session()->getFlashdata('categoryErrors') ?? [],
            session()->getFlashdata('productErrors') ?? [],
        );

        return view($view, [
            'title' => ($mode === 'create' ? 'Tambah ' : 'Edit ') . $config['label'],
            'user' => session('user'),
            'config' => $config,
            'entity' => $entity,
            'extra' => $this->extra($entity, $id),
            'mode' => $mode,
            'row' => $row,
            'fields' => $fields,
            'hidden' => $hidden,
            'errors' => $errors,
            'action' => site_url(str_replace('{id}', (string) $id, $mode === 'create' ? $config['create'] : $config['update'])),
        ]);
    }

    private function extra(string $entity, ?int $id): array
    {
        if ($id === null) {
            return [];
        }
        $db = db_connect();

        return match ($entity) {
            'product' => [
                'variants' => $db->table('product_variants')->where('product_id', $id)->orderBy('is_default', 'DESC')->orderBy('price')->get()->getResultArray(),
                'images' => $db->table('product_images')->where('product_id', $id)->orderBy('sort_order')->get()->getResultArray(),
            ],
            'item' => ['conversions' => $db->table('unit_conversions')->where('inventory_item_id', $id)->orderBy('purchase_unit')->get()->getResultArray()],
            'topping' => [
                'lines' => $db->table('topping_recipe_items')
                    ->select('topping_recipe_items.id, topping_recipe_items.quantity, inventory_items.name AS item_name, inventory_items.base_unit')
                    ->join('inventory_items', 'inventory_items.id = topping_recipe_items.inventory_item_id')
                    ->where('topping_id', $id)->orderBy('inventory_items.name')->get()->getResultArray(),
                'items' => $db->table('inventory_items')->where('is_active', true)->orderBy('name')->get()->getResultArray(),
            ],
            default => [],
        };
    }

    private function options(string $source): array
    {
        $db = db_connect();
        if ($source === 'expense_categories') {
            return array_column($db->table('expense_categories')->where('is_active', true)->orderBy('name')->get()->getResultArray(), 'name', 'id');
        }
        if ($source === 'suppliers') {
            return array_column($db->table('suppliers')->where('is_active', true)->orderBy('name')->get()->getResultArray(), 'name', 'id');
        }
        if ($source === 'categories') {
            return array_column($db->table('categories')->orderBy('name')->get()->getResultArray(), 'name', 'id');
        }
        $items = [];
        foreach ($db->table('inventory_items')->where('is_active', true)->orderBy('name')->get()->getResultArray() as $item) {
            $items[$item['id']] = $item['name'] . ' (' . $item['base_unit'] . ')';
        }

        return $items;
    }

    private function entities(): array
    {
        $paymentSources = ['cash_drawer' => 'Laci kasir aktif', 'main_cash' => 'Kas utama', 'bank' => 'Bank'];
        $variantFields = [
            ['name' => 'variant_name', 'col' => 'name', 'label' => 'Nama varian', 'max' => 100, 'required' => true],
            ['name' => 'sku', 'label' => 'SKU unik', 'max' => 80, 'required' => true, 'upper' => true],
            ['name' => 'price', 'label' => 'Harga jual', 'type' => 'money', 'required' => true],
            ['name' => 'stock_mode', 'label' => 'Pengurangan stok', 'type' => 'select', 'options' => self::STOCK_MODES, 'required' => true],
            ['name' => 'direct_inventory_item_id', 'label' => 'Item stok langsung', 'hint' => 'Khusus mode stok langsung.', 'type' => 'select', 'source' => 'inventory_items', 'blank' => 'Pilih item persediaan'],
        ];

        return [
            'category' => [
                'plural' => 'kategori', 'order' => 'sort_order, name',
                'columns' => ['name' => 'Nama', 'description' => 'Keterangan', 'sort_order' => 'Urutan', 'is_active' => '@active'],
                'toggle' => ['panel/catalog/categories/status', 'category_id', 'kategori'], 'delete' => ['panel/catalog/categories/delete', 'category_id'],
                'label' => 'kategori', 'table' => 'categories', 'back' => 'panel/catalog', 'idField' => 'category_id',
                'create' => 'panel/catalog/categories', 'update' => 'panel/catalog/categories/update',
                'intro' => 'Kategori aktif menjadi filter di katalog publik.',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama kategori', 'max' => 100, 'required' => true],
                    ['name' => 'description', 'label' => 'Keterangan', 'type' => 'textarea', 'max' => 255, 'wide' => true],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'default' => '0', 'required' => true],
                ],
            ],
            'product' => [
                'plural' => 'produk', 'order' => 'products.name', 'select' => ', categories.name AS category_name', 'join' => [['categories', 'categories.id = products.category_id']],
                'columns' => ['name' => 'Nama', 'category_name' => 'Kategori', 'product_type' => '@type', 'is_featured' => '@yesno:Unggulan', 'is_active' => '@active'],
                'toggle' => ['panel/catalog/products/status', 'product_id', 'produk'], 'delete' => ['panel/catalog/products/delete', 'product_id'],
                'extraLinks' => ['Varian' => 'panel/data/variant/create?product_id={id}'],
                'label' => 'produk', 'table' => 'products', 'back' => 'panel/catalog', 'idField' => 'product_id',
                'create' => 'panel/catalog/products', 'update' => 'panel/catalog/products/update',
                'intro' => 'Satu varian awal dibuat bersama produk baru.',
                'fields' => array_merge([
                    ['name' => 'name', 'label' => 'Nama produk', 'max' => 140, 'required' => true],
                    ['name' => 'category_id', 'label' => 'Kategori', 'type' => 'select', 'source' => 'categories', 'blank' => 'Pilih kategori', 'required' => true],
                    ['name' => 'description', 'label' => 'Deskripsi singkat', 'type' => 'textarea', 'max' => 1000, 'wide' => true],
                    ['name' => 'product_type', 'label' => 'Jenis produk', 'type' => 'select', 'options' => ['prepared' => 'Dibuat dari resep', 'stocked' => 'Barang siap jual'], 'required' => true],
                    ['name' => 'is_featured', 'label' => 'Jadikan menu unggulan', 'type' => 'checkbox'],
                ], array_map(static fn (array $f) => $f + ['only' => 'create'], $variantFields)),
            ],
            'variant' => [
                'label' => 'varian harga', 'table' => 'product_variants', 'back' => 'panel/catalog', 'idField' => 'variant_id',
                'create' => 'panel/catalog/variants', 'update' => 'panel/catalog/variants/update',
                'hiddenOnCreate' => ['product_id' => '@query'],
                'fields' => array_merge($variantFields, [['name' => 'is_active', 'label' => 'Varian aktif', 'type' => 'checkbox', 'only' => 'edit']]),
            ],
            'supplier' => [
                'plural' => 'supplier', 'order' => 'name',
                'columns' => ['name' => 'Nama', 'contact_name' => 'Kontak', 'phone' => 'Telepon', 'is_active' => '@active'],
                'toggle' => ['panel/master/suppliers/status', 'supplier_id', 'supplier'], 'delete' => ['panel/master/suppliers/delete', 'supplier_id'],
                'label' => 'supplier', 'table' => 'suppliers', 'back' => 'panel/master', 'idField' => 'supplier_id',
                'create' => 'panel/master/suppliers', 'update' => 'panel/master/suppliers/update',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama supplier', 'max' => 140, 'required' => true],
                    ['name' => 'contact_name', 'label' => 'Nama kontak', 'max' => 120],
                    ['name' => 'phone', 'label' => 'Telepon', 'max' => 40],
                    ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea', 'max' => 500, 'wide' => true],
                ],
            ],
            'item' => [
                'plural' => 'bahan', 'order' => 'name',
                'columns' => ['name' => 'Nama', 'sku' => 'SKU', 'base_unit' => 'Satuan', 'minimum_stock' => 'Stok minimum', 'is_active' => '@active'],
                'toggle' => ['panel/master/items/status', 'item_id', 'bahan'], 'delete' => ['panel/master/items/delete', 'item_id'],
                'label' => 'bahan', 'table' => 'inventory_items', 'back' => 'panel/master', 'idField' => 'item_id',
                'create' => 'panel/master/items', 'update' => 'panel/master/items/update',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama bahan', 'max' => 140, 'required' => true],
                    ['name' => 'sku', 'label' => 'SKU unik', 'max' => 80, 'required' => true, 'upper' => true],
                    ['name' => 'base_unit', 'label' => 'Satuan dasar', 'type' => 'select', 'options' => self::UNITS, 'required' => true],
                    ['name' => 'minimum_stock', 'label' => 'Stok minimum', 'type' => 'number', 'step' => 'any', 'default' => '0', 'required' => true],
                ],
            ],
            'topping' => [
                'plural' => 'topping', 'order' => 'name',
                'columns' => ['name' => 'Nama', 'sku' => 'SKU', 'additional_price' => '@money:Harga tambahan', 'is_active' => '@active'],
                'toggle' => ['panel/master/toppings/status', 'topping_id', 'topping'], 'delete' => ['panel/master/toppings/delete', 'topping_id'],
                'label' => 'topping', 'table' => 'toppings', 'back' => 'panel/master', 'idField' => 'topping_id',
                'create' => 'panel/master/toppings', 'update' => 'panel/master/toppings/update',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama topping', 'max' => 120, 'required' => true],
                    ['name' => 'sku', 'label' => 'SKU unik', 'max' => 80, 'required' => true, 'upper' => true],
                    ['name' => 'additional_price', 'label' => 'Harga tambahan', 'type' => 'money', 'default' => '0', 'required' => true],
                ],
            ],
            'banner' => [
                'plural' => 'banner', 'order' => 'sort_order, id DESC',
                'columns' => ['title' => 'Judul', 'sort_order' => 'Urutan', 'starts_at' => 'Mulai', 'ends_at' => 'Selesai', 'is_active' => '@active'],
                'toggle' => ['panel/settings/banners/status', 'banner_id', 'banner'],
                'label' => 'banner', 'table' => 'banners', 'back' => 'panel/settings', 'idField' => 'banner_id', 'multipart' => true,
                'create' => 'panel/settings/banners', 'update' => 'panel/settings/banners/update',
                'intro' => 'Gunakan rasio 16:5. Banner tanpa jadwal langsung tersedia setelah diaktifkan.',
                'fields' => [
                    ['name' => 'title', 'label' => 'Judul internal', 'max' => 140, 'required' => true],
                    ['name' => 'alt_text', 'label' => 'Teks alternatif', 'max' => 180, 'min' => 5, 'required' => true, 'placeholder' => 'Jelaskan isi gambar'],
                    ['name' => 'image', 'label' => 'Gambar (maksimal 3 MB)', 'type' => 'file', 'required' => true, 'only' => 'create', 'wide' => true],
                    ['name' => 'link_url', 'label' => 'Tautan', 'placeholder' => '/menu atau https://...'],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'default' => '0', 'required' => true],
                    ['name' => 'starts_at', 'label' => 'Mulai tampil', 'type' => 'datetime-local'],
                    ['name' => 'ends_at', 'label' => 'Selesai tampil', 'type' => 'datetime-local'],
                ],
            ],
            'expense' => [
                'label' => 'pengeluaran', 'table' => 'expenses', 'back' => 'panel/expenses', 'idField' => 'expense_id', 'multipart' => true, 'staff' => true,
                'create' => 'panel/expenses/create', 'update' => 'panel/expenses/{id}/update',
                'intro' => 'Pembelian bahan dicatat melalui penerimaan stok agar nilai persediaan tidak menjadi biaya ganda.',
                'fields' => [
                    ['name' => 'document_date', 'label' => 'Tanggal dokumen', 'type' => 'date', 'default' => '@today', 'required' => true],
                    ['name' => 'expense_category_id', 'label' => 'Kategori', 'type' => 'select', 'source' => 'expense_categories', 'blank' => 'Pilih kategori', 'required' => true],
                    ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea', 'min' => 5, 'max' => 500, 'required' => true],
                    ['name' => 'amount', 'label' => 'Nominal', 'type' => 'money', 'minv' => 1, 'step' => '0.01', 'required' => true],
                    ['name' => 'payment_source', 'label' => 'Sumber pembayaran', 'type' => 'select', 'options' => $paymentSources, 'hint' => 'Laci kasir memerlukan shift aktif.', 'required' => true],
                    ['name' => 'payee', 'label' => 'Penerima', 'max' => 160],
                    ['name' => 'evidence', 'label' => 'Bukti (JPEG, PNG, WebP, atau PDF)', 'type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp,application/pdf'],
                ],
            ],
            'receipt' => [
                'label' => 'draft penerimaan', 'table' => 'goods_receipts', 'back' => 'panel/inventory', 'idField' => 'receipt_id',
                'create' => 'panel/inventory/receipts',
                'fields' => [
                    ['name' => 'reference_number', 'label' => 'Nomor referensi', 'max' => 80, 'required' => true, 'upper' => true, 'placeholder' => 'Contoh: PB-2026-001'],
                    ['name' => 'document_date', 'label' => 'Tanggal dokumen', 'type' => 'date', 'default' => '@today', 'required' => true],
                    ['name' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'source' => 'suppliers', 'blank' => 'Tanpa supplier'],
                    ['name' => 'payment_source', 'label' => 'Sumber pembayaran', 'type' => 'select', 'options' => ['cash' => 'Kas', 'bank' => 'Bank', 'payable' => 'Utang usaha'], 'required' => true],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea', 'max' => 500, 'wide' => true],
                ],
            ],
            'cashier' => [
                'plural' => 'akun kasir', 'order' => 'name',
                'columns' => ['name' => 'Nama', 'username' => 'Username', 'email' => 'Email', 'status' => '@status'],
                'toggle' => ['panel/users/status', 'user_id', 'akun kasir'],
                'label' => 'akun kasir', 'table' => 'users', 'back' => 'panel/users', 'idField' => 'user_id',
                'create' => 'panel/users/create', 'update' => 'panel/users/update', 'where' => ['role' => 'cashier'],
                'intro' => 'Reset password dilakukan dari daftar akun kasir.',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama lengkap', 'max' => 120, 'required' => true],
                    ['name' => 'username', 'label' => 'Username', 'max' => 80, 'required' => true, 'autocomplete' => 'username'],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'max' => 190, 'autocomplete' => 'email'],
                    ['name' => 'password', 'label' => 'Password awal (minimal 12 karakter)', 'type' => 'password', 'min' => 12, 'required' => true, 'autocomplete' => 'new-password', 'only' => 'create'],
                ],
            ],
        ];
    }
}
