<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Models\InventoryItemModel;
use App\Models\ProductVariantModel;
use App\Models\SupplierModel;
use CodeIgniter\HTTP\RedirectResponse;

class MasterDataController extends BaseController
{
    public function index(): string
    {
        $database = db_connect();
        $variants = $database->table('product_variants')
            ->select('product_variants.id, product_variants.name AS variant_name, product_variants.sku, products.name AS product_name')
            ->join('products', 'products.id = product_variants.product_id')
            ->where('product_variants.stock_mode', 'recipe')
            ->where('product_variants.is_active', true)
            ->orderBy('products.name')->orderBy('product_variants.name')->get()->getResultArray();
        $recipeItems = $database->table('recipe_items')
            ->select('recipe_items.*, recipes.product_variant_id, inventory_items.name AS item_name, inventory_items.base_unit')
            ->join('recipes', 'recipes.id = recipe_items.recipe_id')
            ->join('inventory_items', 'inventory_items.id = recipe_items.inventory_item_id')
            ->where('recipes.is_active', true)
            ->orderBy('inventory_items.name')->get()->getResultArray();
        $recipesByVariant = [];
        foreach ($recipeItems as $item) {
            $recipesByVariant[$item['product_variant_id']][] = $item;
        }
        $toppings = $database->table('toppings')->orderBy('name')->get()->getResultArray();
        $toppingRecipeItems = $database->table('topping_recipe_items')
            ->select('topping_recipe_items.*, inventory_items.name AS item_name, inventory_items.base_unit')
            ->join('inventory_items', 'inventory_items.id = topping_recipe_items.inventory_item_id')
            ->orderBy('inventory_items.name')->get()->getResultArray();
        $toppingItemsByTopping = [];
        foreach ($toppingRecipeItems as $item) {
            $toppingItemsByTopping[$item['topping_id']][] = $item;
        }

        return view('panel/master/index', [
            'title' => 'Bahan, resep, dan supplier',
            'user' => session('user'),
            'suppliers' => (new SupplierModel())->orderBy('name')->findAll(),
            'items' => (new InventoryItemModel())->orderBy('name')->findAll(),
            'conversions' => $database->table('unit_conversions')->orderBy('purchase_unit')->get()->getResultArray(),
            'variants' => $variants,
            'recipesByVariant' => $recipesByVariant,
            'toppings' => $toppings,
            'toppingItemsByTopping' => $toppingItemsByTopping,
        ]);
    }

    public function createSupplier(): RedirectResponse
    {
        $rules = [
            'name' => 'required|min_length[2]|max_length[140]',
            'contact_name' => 'permit_empty|max_length[120]',
            'phone' => 'permit_empty|max_length[40]',
            'address' => 'permit_empty|max_length[500]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        (new SupplierModel())->insert([
            'name' => trim((string) $this->request->getPost('name')),
            'contact_name' => trim((string) $this->request->getPost('contact_name')) ?: null,
            'phone' => trim((string) $this->request->getPost('phone')) ?: null,
            'address' => trim((string) $this->request->getPost('address')) ?: null,
            'is_active' => true,
        ]);
        return redirect()->route('panel.master')->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function toggleSupplier(): RedirectResponse
    {
        $id = filter_var($this->request->getPost('supplier_id'), FILTER_VALIDATE_INT);
        $model = new SupplierModel();
        $supplier = $id ? $model->find($id) : null;
        if (!$supplier) {
            return redirect()->route('panel.master')->with('error', 'Supplier tidak ditemukan.');
        }
        $model->update($supplier['id'], ['is_active' => !$supplier['is_active']]);
        return redirect()->route('panel.master')->with('success', $supplier['is_active'] ? 'Supplier dinonaktifkan.' : 'Supplier diaktifkan.');
    }

    public function updateSupplier(): RedirectResponse
    {
        $id = filter_var($this->request->getPost('supplier_id'), FILTER_VALIDATE_INT);
        $model = new SupplierModel();
        $supplier = $id ? $model->find($id) : null;
        if (!$supplier) {
            return redirect()->route('panel.master')->with('error', 'Supplier tidak ditemukan.');
        }
        if (!$this->validate(['name' => 'required|min_length[2]|max_length[140]', 'contact_name' => 'permit_empty|max_length[120]', 'phone' => 'permit_empty|max_length[40]', 'address' => 'permit_empty|max_length[500]'])) {
            return redirect()->route('panel.master')->with('error', implode(' ', $this->validator->getErrors()));
        }
        $model->update($supplier['id'], [
            'name' => trim((string) $this->request->getPost('name')),
            'contact_name' => trim((string) $this->request->getPost('contact_name')) ?: null,
            'phone' => trim((string) $this->request->getPost('phone')) ?: null,
            'address' => trim((string) $this->request->getPost('address')) ?: null,
        ]);
        return redirect()->route('panel.master')->with('success', 'Supplier berhasil diperbarui.');
    }

    public function createItem(): RedirectResponse
    {
        $rules = [
            'name' => 'required|min_length[2]|max_length[140]',
            'sku' => 'required|alpha_numeric_punct|min_length[2]|max_length[80]|is_unique[inventory_items.sku]',
            'base_unit' => 'required|in_list[gram,mililiter,pcs]',
            'minimum_stock' => 'required|decimal|greater_than_equal_to[0]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        (new InventoryItemModel())->insert([
            'name' => trim((string) $this->request->getPost('name')),
            'sku' => strtoupper(trim((string) $this->request->getPost('sku'))),
            'base_unit' => (string) $this->request->getPost('base_unit'),
            'minimum_stock' => (string) $this->request->getPost('minimum_stock'),
            'quantity_on_hand' => 0,
            'average_cost' => 0,
            'is_active' => true,
        ]);
        return redirect()->route('panel.master')->with('success', 'Bahan berhasil ditambahkan.');
    }

    public function toggleItem(): RedirectResponse
    {
        $id = filter_var($this->request->getPost('item_id'), FILTER_VALIDATE_INT);
        $model = new InventoryItemModel();
        $item = $id ? $model->find($id) : null;
        if (!$item) {
            return redirect()->route('panel.master')->with('error', 'Bahan tidak ditemukan.');
        }
        $model->update($item['id'], ['is_active' => !$item['is_active']]);
        return redirect()->route('panel.master')->with('success', $item['is_active'] ? 'Bahan dinonaktifkan.' : 'Bahan diaktifkan.');
    }

    public function updateItem(): RedirectResponse
    {
        $id = filter_var($this->request->getPost('item_id'), FILTER_VALIDATE_INT);
        $model = new InventoryItemModel();
        $item = $id ? $model->find($id) : null;
        if (!$item) {
            return redirect()->route('panel.master')->with('error', 'Bahan tidak ditemukan.');
        }
        $rules = [
            'name' => 'required|min_length[2]|max_length[140]',
            'sku' => 'required|alpha_numeric_punct|min_length[2]|max_length[80]|is_unique[inventory_items.sku,id,' . $item['id'] . ']',
            'base_unit' => 'required|in_list[gram,mililiter,pcs]',
            'minimum_stock' => 'required|decimal|greater_than_equal_to[0]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->route('panel.master')->with('error', implode(' ', $this->validator->getErrors()));
        }
        $model->update($item['id'], [
            'name' => trim((string) $this->request->getPost('name')),
            'sku' => strtoupper(trim((string) $this->request->getPost('sku'))),
            'base_unit' => (string) $this->request->getPost('base_unit'),
            'minimum_stock' => (string) $this->request->getPost('minimum_stock'),
        ]);
        return redirect()->route('panel.master')->with('success', 'Bahan berhasil diperbarui.');
    }

    public function saveConversion(): RedirectResponse
    {
        $rules = [
            'item_id' => 'required|is_natural_no_zero',
            'purchase_unit' => 'required|min_length[1]|max_length[30]',
            'factor_to_base' => 'required|decimal|greater_than[0]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->route('panel.master')->with('error', implode(' ', $this->validator->getErrors()));
        }
        $item = (new InventoryItemModel())->find((int) $this->request->getPost('item_id'));
        if (!$item) {
            return redirect()->route('panel.master')->with('error', 'Bahan tidak ditemukan.');
        }
        $database = db_connect();
        $data = [
            'inventory_item_id' => $item['id'],
            'purchase_unit' => strtolower(trim((string) $this->request->getPost('purchase_unit'))),
            'factor_to_base' => (string) $this->request->getPost('factor_to_base'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $existing = $database->table('unit_conversions')->where('inventory_item_id', $item['id'])->where('purchase_unit', $data['purchase_unit'])->get()->getRowArray();
        if ($existing) {
            $database->table('unit_conversions')->where('id', $existing['id'])->update($data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $database->table('unit_conversions')->insert($data);
        }
        return redirect()->route('panel.master')->with('success', 'Konversi satuan disimpan.');
    }

    public function saveRecipeItem(): RedirectResponse
    {
        $rules = [
            'variant_id' => 'required|is_natural_no_zero',
            'item_id' => 'required|is_natural_no_zero',
            'quantity' => 'required|decimal|greater_than[0]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->route('panel.master')->with('error', implode(' ', $this->validator->getErrors()));
        }
        $variantId = (int) $this->request->getPost('variant_id');
        $itemId = (int) $this->request->getPost('item_id');
        $variant = (new ProductVariantModel())->where('stock_mode', 'recipe')->find($variantId);
        $item = (new InventoryItemModel())->find($itemId);
        if (!$variant || !$item) {
            return redirect()->route('panel.master')->with('error', 'Varian atau bahan tidak ditemukan.');
        }

        $database = db_connect();
        $database->transStart();
        $recipe = $database->table('recipes')->where('product_variant_id', $variantId)->where('is_active', true)->get()->getRowArray();
        if (!$recipe) {
            $database->table('recipes')->insert(['product_variant_id' => $variantId, 'version' => 1, 'is_active' => true, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            $recipe = ['id' => $database->insertID()];
        }
        $existing = $database->table('recipe_items')->where('recipe_id', $recipe['id'])->where('inventory_item_id', $itemId)->get()->getRowArray();
        $data = ['recipe_id' => $recipe['id'], 'inventory_item_id' => $itemId, 'quantity' => (string) $this->request->getPost('quantity'), 'updated_at' => date('Y-m-d H:i:s')];
        if ($existing) {
            $database->table('recipe_items')->where('id', $existing['id'])->update($data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $database->table('recipe_items')->insert($data);
        }
        $database->transComplete();
        if (!$database->transStatus()) {
            return redirect()->route('panel.master')->with('error', 'Resep belum dapat disimpan.');
        }
        return redirect()->route('panel.master')->with('success', 'Takaran bahan pada resep disimpan.');
    }

    public function removeRecipeItem(): RedirectResponse
    {
        $id = filter_var($this->request->getPost('recipe_item_id'), FILTER_VALIDATE_INT);
        if (!$id || !db_connect()->table('recipe_items')->where('id', $id)->delete()) {
            return redirect()->route('panel.master')->with('error', 'Bahan resep tidak ditemukan.');
        }
        return redirect()->route('panel.master')->with('success', 'Bahan dihapus dari resep.');
    }

    public function createTopping(): RedirectResponse
    {
        $rules = [
            'name' => 'required|min_length[2]|max_length[120]',
            'sku' => 'required|alpha_numeric_punct|min_length[2]|max_length[80]|is_unique[toppings.sku]',
            'additional_price' => 'required|decimal|greater_than_equal_to[0]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->route('panel.master')->with('error', implode(' ', $this->validator->getErrors()));
        }
        db_connect()->table('toppings')->insert([
            'name' => trim((string) $this->request->getPost('name')),
            'sku' => strtoupper(trim((string) $this->request->getPost('sku'))),
            'additional_price' => (string) $this->request->getPost('additional_price'),
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return redirect()->route('panel.master')->with('success', 'Topping berhasil ditambahkan.');
    }

    public function toggleTopping(): RedirectResponse
    {
        $id = filter_var($this->request->getPost('topping_id'), FILTER_VALIDATE_INT);
        $database = db_connect();
        $topping = $id ? $database->table('toppings')->where('id', $id)->get()->getRowArray() : null;
        if (!$topping) {
            return redirect()->route('panel.master')->with('error', 'Topping tidak ditemukan.');
        }
        $database->table('toppings')->where('id', $topping['id'])->update(['is_active' => !$topping['is_active'], 'updated_at' => date('Y-m-d H:i:s')]);
        return redirect()->route('panel.master')->with('success', $topping['is_active'] ? 'Topping dinonaktifkan.' : 'Topping diaktifkan.');
    }

    public function updateTopping(): RedirectResponse
    {
        $id = filter_var($this->request->getPost('topping_id'), FILTER_VALIDATE_INT);
        $database = db_connect();
        $topping = $id ? $database->table('toppings')->where('id', $id)->get()->getRowArray() : null;
        if (!$topping) {
            return redirect()->route('panel.master')->with('error', 'Topping tidak ditemukan.');
        }
        $rules = [
            'name' => 'required|min_length[2]|max_length[120]',
            'sku' => 'required|alpha_numeric_punct|min_length[2]|max_length[80]|is_unique[toppings.sku,id,' . $topping['id'] . ']',
            'additional_price' => 'required|decimal|greater_than_equal_to[0]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->route('panel.master')->with('error', implode(' ', $this->validator->getErrors()));
        }
        $database->table('toppings')->where('id', $topping['id'])->update([
            'name' => trim((string) $this->request->getPost('name')),
            'sku' => strtoupper(trim((string) $this->request->getPost('sku'))),
            'additional_price' => (string) $this->request->getPost('additional_price'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return redirect()->route('panel.master')->with('success', 'Topping berhasil diperbarui.');
    }

    public function saveToppingItem(): RedirectResponse
    {
        $rules = [
            'topping_id' => 'required|is_natural_no_zero',
            'item_id' => 'required|is_natural_no_zero',
            'quantity' => 'required|decimal|greater_than[0]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->route('panel.master')->with('error', implode(' ', $this->validator->getErrors()));
        }
        $database = db_connect();
        $toppingId = (int) $this->request->getPost('topping_id');
        $itemId = (int) $this->request->getPost('item_id');
        if (!$database->table('toppings')->where('id', $toppingId)->countAllResults() || !(new InventoryItemModel())->find($itemId)) {
            return redirect()->route('panel.master')->with('error', 'Topping atau bahan tidak ditemukan.');
        }
        $existing = $database->table('topping_recipe_items')->where('topping_id', $toppingId)->where('inventory_item_id', $itemId)->get()->getRowArray();
        $data = ['topping_id' => $toppingId, 'inventory_item_id' => $itemId, 'quantity' => (string) $this->request->getPost('quantity'), 'updated_at' => date('Y-m-d H:i:s')];
        if ($existing) {
            $database->table('topping_recipe_items')->where('id', $existing['id'])->update($data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $database->table('topping_recipe_items')->insert($data);
        }
        return redirect()->route('panel.master')->with('success', 'Pemakaian bahan topping disimpan.');
    }
}
