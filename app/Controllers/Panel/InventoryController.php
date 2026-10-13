<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Models\InventoryItemModel;
use App\Models\SupplierModel;
use App\Services\InventoryLockService;
use App\Services\AccountingService;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

class InventoryController extends BaseController
{
    public function index(): string
    {
        $database = db_connect();
        $receipts = $database->table('goods_receipts')
            ->select('goods_receipts.*, suppliers.name AS supplier_name, users.name AS creator_name')
            ->join('suppliers', 'suppliers.id = goods_receipts.supplier_id', 'left')
            ->join('users', 'users.id = goods_receipts.created_by')
            ->orderBy('goods_receipts.id', 'DESC')->limit(30)->get()->getResultArray();
        $receiptItems = $database->table('goods_receipt_items')
            ->select('goods_receipt_items.*, inventory_items.name AS item_name, inventory_items.base_unit')
            ->join('inventory_items', 'inventory_items.id = goods_receipt_items.inventory_item_id')
            ->orderBy('goods_receipt_items.id')->get()->getResultArray();
        $itemsByReceipt = [];
        foreach ($receiptItems as $item) {
            $itemsByReceipt[$item['goods_receipt_id']][] = $item;
        }
        $conversionsByItem = [];
        foreach ($database->table('unit_conversions')->get()->getResultArray() as $conversion) {
            $conversionsByItem[$conversion['inventory_item_id']][] = $conversion;
        }

        return view('panel/inventory/index', [
            'title' => 'Penerimaan dan kartu stok',
            'user' => session('user'),
            'items' => (new InventoryItemModel())->where('is_active', true)->orderBy('name')->findAll(),
            'suppliers' => (new SupplierModel())->where('is_active', true)->orderBy('name')->findAll(),
            'receipts' => $receipts,
            'itemsByReceipt' => $itemsByReceipt,
            'conversionsByItem' => $conversionsByItem,
            'movements' => $database->table('stock_movements')->select('stock_movements.*, inventory_items.name AS item_name, inventory_items.base_unit')->join('inventory_items', 'inventory_items.id = stock_movements.inventory_item_id')->orderBy('posted_at', 'DESC')->limit(50)->get()->getResultArray(),
        ]);
    }

    public function createReceipt(): RedirectResponse
    {
        $rules = [
            'reference_number' => 'required|min_length[2]|max_length[80]|is_unique[goods_receipts.reference_number]',
            'supplier_id' => 'permit_empty|is_natural_no_zero',
            'document_date' => 'required|valid_date[Y-m-d]',
            'payment_source' => 'required|in_list[cash,bank,payable]',
            'notes' => 'permit_empty|max_length[500]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        $supplierId = $this->request->getPost('supplier_id') ? (int) $this->request->getPost('supplier_id') : null;
        if ($supplierId && !(new SupplierModel())->find($supplierId)) {
            return redirect()->back()->withInput()->with('error', 'Supplier tidak ditemukan.');
        }
        db_connect()->table('goods_receipts')->insert([
            'reference_number' => strtoupper(trim((string) $this->request->getPost('reference_number'))),
            'supplier_id' => $supplierId,
            'document_date' => (string) $this->request->getPost('document_date'),
            'payment_source' => (string) $this->request->getPost('payment_source'),
            'status' => 'draft',
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            'created_by' => (int) session('user_id'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return redirect()->route('panel.inventory')->with('success', 'Draft penerimaan berhasil dibuat.');
    }

    public function addReceiptItem(): RedirectResponse
    {
        $rules = [
            'receipt_id' => 'required|is_natural_no_zero',
            'item_id' => 'required|is_natural_no_zero',
            'purchase_quantity' => 'required|decimal|greater_than[0]',
            'purchase_unit' => 'required|min_length[1]|max_length[30]',
            'purchase_unit_cost' => 'required|decimal|greater_than_equal_to[0]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->route('panel.inventory')->with('error', implode(' ', $this->validator->getErrors()));
        }
        $database = db_connect();
        $receiptId = (int) $this->request->getPost('receipt_id');
        $itemId = (int) $this->request->getPost('item_id');
        $receipt = $database->table('goods_receipts')->where('id', $receiptId)->where('status', 'draft')->get()->getRowArray();
        $item = (new InventoryItemModel())->find($itemId);
        if (!$receipt || !$item) {
            return redirect()->route('panel.inventory')->with('error', 'Draft penerimaan atau bahan tidak ditemukan.');
        }
        $purchaseUnit = strtolower(trim((string) $this->request->getPost('purchase_unit')));
        $factor = $purchaseUnit === $item['base_unit'] ? 1.0 : (float) ($database->table('unit_conversions')->where('inventory_item_id', $itemId)->where('purchase_unit', $purchaseUnit)->get()->getRow('factor_to_base') ?? 0);
        if ($factor <= 0) {
            return redirect()->route('panel.inventory')->with('error', 'Konversi satuan pembelian belum tersedia untuk bahan tersebut.');
        }
        $purchaseQuantity = (float) $this->request->getPost('purchase_quantity');
        $purchaseCost = (float) $this->request->getPost('purchase_unit_cost');
        $baseQuantity = $purchaseQuantity * $factor;
        $data = [
            'goods_receipt_id' => $receiptId,
            'inventory_item_id' => $itemId,
            'purchase_quantity' => $purchaseQuantity,
            'purchase_unit' => $purchaseUnit,
            'factor_to_base' => $factor,
            'base_quantity' => $baseQuantity,
            'purchase_unit_cost' => $purchaseCost,
            'base_unit_cost' => $baseQuantity > 0 ? ($purchaseQuantity * $purchaseCost) / $baseQuantity : 0,
            'line_total' => $purchaseQuantity * $purchaseCost,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $existing = $database->table('goods_receipt_items')->where('goods_receipt_id', $receiptId)->where('inventory_item_id', $itemId)->get()->getRowArray();
        if ($existing) {
            $database->table('goods_receipt_items')->where('id', $existing['id'])->update($data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $database->table('goods_receipt_items')->insert($data);
        }
        return redirect()->route('panel.inventory')->with('success', 'Item penerimaan disimpan.');
    }

    public function postReceipt(): RedirectResponse
    {
        $receiptId = filter_var($this->request->getPost('receipt_id'), FILTER_VALIDATE_INT);
        if (!$receiptId) {
            return redirect()->route('panel.inventory')->with('error', 'Penerimaan tidak ditemukan.');
        }
        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            $receipt = $database->query('SELECT * FROM goods_receipts WHERE id = ? FOR UPDATE', [$receiptId])->getRowArray();
            if (!$receipt || $receipt['status'] !== 'draft') {
                throw new RuntimeException('Penerimaan sudah diproses atau tidak ditemukan.');
            }
            $lines = $database->table('goods_receipt_items')->where('goods_receipt_id', $receiptId)->get()->getResultArray();
            if ($lines === []) {
                throw new RuntimeException('Tambahkan minimal satu item sebelum posting.');
            }
            usort($lines, static fn (array $left, array $right): int => (int) $left['inventory_item_id'] <=> (int) $right['inventory_item_id']);
            $lockedItems = [];
            foreach ($lines as $line) {
                $lockedItems[$line['inventory_item_id']] = $database->query('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$line['inventory_item_id']])->getRowArray();
            }
            (new InventoryLockService($database))->assertUnlocked(array_column($lines, 'inventory_item_id'));
            $postedAt = date('Y-m-d H:i:s');
            foreach ($lines as $line) {
                $item = $lockedItems[$line['inventory_item_id']];
                $oldQuantity = (float) $item['quantity_on_hand'];
                $oldValue = $oldQuantity * (float) $item['average_cost'];
                $addedQuantity = (float) $line['base_quantity'];
                $addedValue = (float) $line['line_total'];
                $newQuantity = $oldQuantity + $addedQuantity;
                $newAverage = $newQuantity > 0 ? ($oldValue + $addedValue) / $newQuantity : 0;
                $database->table('inventory_items')->where('id', $item['id'])->update(['quantity_on_hand' => $newQuantity, 'average_cost' => $newAverage, 'updated_at' => $postedAt]);
                $database->table('stock_movements')->insert([
                    'inventory_item_id' => $item['id'], 'quantity' => $addedQuantity, 'unit_cost' => $line['base_unit_cost'], 'total_cost' => $addedValue,
                    'movement_type' => 'receipt', 'source_type' => 'goods_receipt', 'source_id' => $receiptId, 'notes' => $receipt['reference_number'],
                    'posted_at' => $postedAt, 'created_by' => (int) session('user_id'), 'created_at' => $postedAt,
                ]);
            }
            $receiptTotal = array_sum(array_map(static fn (array $line): float => (float) $line['line_total'], $lines));
            $creditAccount = match ($receipt['payment_source']) { 'cash' => 'main_cash', 'bank' => 'bank', default => 'accounts_payable' };
            (new AccountingService($database))->post('goods_receipt', $receiptId, date('Y-m-d', strtotime($postedAt)), 'Penerimaan stok ' . $receipt['reference_number'], [
                ['account_key' => 'inventory', 'debit' => $receiptTotal, 'credit' => 0],
                ['account_key' => $creditAccount, 'debit' => 0, 'credit' => $receiptTotal],
            ], (int) session('user_id'));
            $database->table('goods_receipts')->where('id', $receiptId)->update(['status' => 'posted', 'posted_by' => (int) session('user_id'), 'posted_at' => $postedAt, 'updated_at' => $postedAt]);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->route('panel.inventory')->with('error', $exception->getMessage());
        }
        return redirect()->route('panel.inventory')->with('success', 'Penerimaan diposting dan saldo stok diperbarui.');
    }
}
