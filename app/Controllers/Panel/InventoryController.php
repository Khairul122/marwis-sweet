<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Models\InventoryItemModel;
use App\Models\SupplierModel;
use App\Services\InventoryLockService;
use App\Services\AccountingService;
use App\Services\Money;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

class InventoryController extends BaseController
{
    public function index(): string
    {
        $database = db_connect();

        return view('panel/inventory/index', [
            'title' => 'Penerimaan dan kartu stok',
            'user' => session('user'),
            'items' => (new InventoryItemModel())->where('is_active', true)->orderBy('name')->findAll(),
            'receipts' => $database->table('goods_receipts')
                ->select('goods_receipts.*, suppliers.name AS supplier_name, users.name AS creator_name, (SELECT COALESCE(SUM(line_total), 0) FROM goods_receipt_items WHERE goods_receipt_id = goods_receipts.id) AS total', false)
                ->join('suppliers', 'suppliers.id = goods_receipts.supplier_id', 'left')
                ->join('users', 'users.id = goods_receipts.created_by')
                ->orderBy('goods_receipts.id', 'DESC')->limit(200)->get()->getResultArray(),
            'movements' => $database->table('stock_movements')
                ->select('stock_movements.*, inventory_items.name AS item_name, inventory_items.base_unit')
                ->join('inventory_items', 'inventory_items.id = stock_movements.inventory_item_id')
                ->orderBy('posted_at', 'DESC')->orderBy('stock_movements.id', 'DESC')->limit(200)->get()->getResultArray(),
        ]);
    }

    public function receipt(int $receiptId): string
    {
        $database = db_connect();
        $receipt = $database->table('goods_receipts')
            ->select('goods_receipts.*, suppliers.name AS supplier_name, users.name AS creator_name')
            ->join('suppliers', 'suppliers.id = goods_receipts.supplier_id', 'left')
            ->join('users', 'users.id = goods_receipts.created_by')
            ->where('goods_receipts.id', $receiptId)->get()->getRowArray()
            ?? throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();

        return view('panel/inventory/receipt', [
            'title' => 'Penerimaan ' . $receipt['reference_number'],
            'user' => session('user'),
            'receipt' => $receipt,
            'lines' => $database->table('goods_receipt_items')
                ->select('goods_receipt_items.*, inventory_items.name AS item_name, inventory_items.base_unit')
                ->join('inventory_items', 'inventory_items.id = goods_receipt_items.inventory_item_id')
                ->where('goods_receipt_id', $receiptId)->orderBy('goods_receipt_items.id')->get()->getResultArray(),
            'items' => (new InventoryItemModel())->where('is_active', true)->orderBy('name')->findAll(),
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
        $database = db_connect();
        $database->table('goods_receipts')->insert([
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
        return redirect()->to(site_url('panel/inventory/receipts/' . $database->insertID()))->with('success', 'Draft penerimaan berhasil dibuat. Tambahkan item bahan yang diterima.');
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
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
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
        $factor = $purchaseUnit === $item['base_unit'] ? '1.000000' : (string) ($database->table('unit_conversions')->where('inventory_item_id', $itemId)->where('purchase_unit', $purchaseUnit)->get()->getRow('factor_to_base') ?? '0');
        if (Money::factorMillionths($factor) <= 0) {
            return redirect()->back()->withInput()->with('error', 'Konversi satuan pembelian belum tersedia untuk bahan tersebut. Tambahkan di halaman edit bahan.');
        }
        $purchaseQuantityMilli = Money::quantityMilli((string) $this->request->getPost('purchase_quantity'));
        $purchaseCostScaled = Money::unitCostTenThousand((string) $this->request->getPost('purchase_unit_cost'));
        $baseQuantityMilli = Money::quantityByFactor($purchaseQuantityMilli, $factor);
        if ($purchaseQuantityMilli <= 0 || $baseQuantityMilli <= 0) {
            return redirect()->back()->withInput()->with('error', 'Jumlah pembelian terlalu kecil untuk satuan dasar item ini.');
        }
        $lineTotalCents = Money::centsForQuantity($purchaseQuantityMilli, $purchaseCostScaled);
        $data = [
            'goods_receipt_id' => $receiptId,
            'inventory_item_id' => $itemId,
            'purchase_quantity' => Money::quantity($purchaseQuantityMilli),
            'purchase_unit' => $purchaseUnit,
            'factor_to_base' => $factor,
            'base_quantity' => Money::quantity($baseQuantityMilli),
            'purchase_unit_cost' => Money::unitCost($purchaseCostScaled),
            'base_unit_cost' => Money::averageUnitCost($lineTotalCents, $baseQuantityMilli),
            'line_total' => Money::decimal($lineTotalCents),
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
                $oldQuantityMilli = Money::quantityMilli($item['quantity_on_hand']);
                $oldValueCents = Money::cents($item['inventory_value']);
                $addedQuantityMilli = Money::quantityMilli($line['base_quantity']);
                $addedValueCents = Money::cents($line['line_total']);
                $newQuantityMilli = $oldQuantityMilli + $addedQuantityMilli;
                $newValueCents = $oldValueCents + $addedValueCents;
                $newAverage = $newQuantityMilli > 0 ? Money::averageUnitCost($newValueCents, $newQuantityMilli) : '0.0000';
                $database->table('inventory_items')->where('id', $item['id'])->update([
                    'quantity_on_hand' => Money::quantity($newQuantityMilli),
                    'average_cost' => $newAverage,
                    'inventory_value' => Money::decimal($newValueCents),
                    'updated_at' => $postedAt,
                ]);
                $database->table('stock_movements')->insert([
                    'inventory_item_id' => $item['id'], 'quantity' => Money::quantity($addedQuantityMilli), 'unit_cost' => $line['base_unit_cost'], 'total_cost' => $line['line_total'],
                    'movement_type' => 'receipt', 'source_type' => 'goods_receipt', 'source_id' => $receiptId, 'notes' => $receipt['reference_number'],
                    'posted_at' => $postedAt, 'created_by' => (int) session('user_id'), 'created_at' => $postedAt,
                ]);
            }
            $receiptTotalCents = array_sum(array_map(static fn (array $line): int => Money::cents($line['line_total']), $lines));
            $creditAccount = match ($receipt['payment_source']) { 'cash' => 'main_cash', 'bank' => 'bank', default => 'accounts_payable' };
            (new AccountingService($database))->post('goods_receipt', $receiptId, date('Y-m-d', strtotime($postedAt)), 'Penerimaan stok ' . $receipt['reference_number'], [
                ['account_key' => 'inventory', 'debit' => Money::decimal($receiptTotalCents), 'credit' => 0],
                ['account_key' => $creditAccount, 'debit' => 0, 'credit' => Money::decimal($receiptTotalCents)],
            ], (int) session('user_id'));
            $database->table('goods_receipts')->where('id', $receiptId)->update(['status' => 'posted', 'posted_by' => (int) session('user_id'), 'posted_at' => $postedAt, 'updated_at' => $postedAt]);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->back()->with('error', $exception->getMessage());
        }
        return redirect()->route('panel.inventory')->with('success', 'Penerimaan diposting dan saldo stok diperbarui.');
    }
}
