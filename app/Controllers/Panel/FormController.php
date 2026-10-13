<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Halaman tambah/edit terpisah. Form hanya menampilkan field; penyimpanan tetap
 * lewat handler POST di controller masing-masing modul.
 */
class FormController extends BaseController
{
    private const UNITS = ['gram' => 'Gram', 'mililiter' => 'Mililiter', 'pcs' => 'Pcs'];
    private const STOCK_MODES = ['recipe' => 'Berdasarkan resep', 'direct' => 'Stok produk langsung', 'none' => 'Tanpa pelacakan stok'];

    public function show(string $entity, ?int $id = null): string
    {
        $config = $this->entities()[$entity] ?? throw PageNotFoundException::forPageNotFound();
        $row = [];
        if ($id !== null) {
            if (!isset($config['update'])) {
                throw PageNotFoundException::forPageNotFound();
            }
            $row = db_connect()->table($config['table'])->where('id', $id)->get()->getRowArray() ?? throw PageNotFoundException::forPageNotFound();
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

        return view('panel/form', [
            'title' => ($mode === 'create' ? 'Tambah ' : 'Edit ') . $config['label'],
            'user' => session('user'),
            'config' => $config,
            'mode' => $mode,
            'row' => $row,
            'fields' => $fields,
            'hidden' => $hidden,
            'errors' => $errors,
            'action' => site_url($mode === 'create' ? $config['create'] : $config['update']),
        ]);
    }

    private function options(string $source): array
    {
        $db = db_connect();
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
        $variantFields = [
            ['name' => 'variant_name', 'col' => 'name', 'label' => 'Nama varian', 'max' => 100, 'required' => true],
            ['name' => 'sku', 'label' => 'SKU unik', 'max' => 80, 'required' => true, 'upper' => true],
            ['name' => 'price', 'label' => 'Harga jual', 'type' => 'money', 'required' => true],
            ['name' => 'stock_mode', 'label' => 'Pengurangan stok', 'type' => 'select', 'options' => self::STOCK_MODES, 'required' => true],
            ['name' => 'direct_inventory_item_id', 'label' => 'Item stok langsung', 'hint' => 'Khusus mode stok langsung.', 'type' => 'select', 'source' => 'inventory_items', 'blank' => 'Pilih item persediaan'],
        ];

        return [
            'category' => [
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
                'label' => 'topping', 'table' => 'toppings', 'back' => 'panel/master', 'idField' => 'topping_id',
                'create' => 'panel/master/toppings', 'update' => 'panel/master/toppings/update',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama topping', 'max' => 120, 'required' => true],
                    ['name' => 'sku', 'label' => 'SKU unik', 'max' => 80, 'required' => true, 'upper' => true],
                    ['name' => 'additional_price', 'label' => 'Harga tambahan', 'type' => 'money', 'default' => '0', 'required' => true],
                ],
            ],
            'banner' => [
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
            'cashier' => [
                'label' => 'akun kasir', 'table' => 'users', 'back' => 'panel/users', 'idField' => 'user_id',
                'create' => 'panel/users/create',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama lengkap', 'max' => 120, 'required' => true],
                    ['name' => 'username', 'label' => 'Username', 'max' => 80, 'required' => true, 'autocomplete' => 'username'],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'max' => 190, 'autocomplete' => 'email'],
                    ['name' => 'password', 'label' => 'Password awal (minimal 12 karakter)', 'type' => 'password', 'min' => 12, 'required' => true, 'autocomplete' => 'new-password'],
                ],
            ],
        ];
    }
}
