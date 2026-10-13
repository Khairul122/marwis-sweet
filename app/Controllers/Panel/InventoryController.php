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
        $user = session('user');
        $isOwner = ($user['role'] ?? '') === 'owner';
        $items = $database->table('inventory_items')->where('is_active', true);
        $items->select($isOwner
            ? '*'
            : 'id, name, sku, quantity_on_hand, minimum_stock, base_unit, is_active');

        $receipts = $database->table('goods_receipts')
            ->select('goods_receipts.*, suppliers.name AS supplier_name, users.name AS creator_name, (SELECT COALESCE(SUM(line_total), 0) FROM goods_receipt_items WHERE goods_receipt_id = goods_receipts.id) AS total', false)
            ->join('suppliers', 'suppliers.id = goods_receipts.supplier_id', 'left')
            ->join('users', 'users.id = goods_receipts.created_by');
        if (!$isOwner) {
            $receipts->where('goods_receipts.created_by', (int) session('user_id'));
        }

        $movementFields = 'stock_movements.id, stock_movements.inventory_item_id, stock_movements.quantity, stock_movements.movement_type, stock_movements.source_type, stock_movements.source_id, stock_movements.notes, stock_movements.posted_at, inventory_items.name AS item_name, inventory_items.base_unit';
        if ($isOwner) {
            $movementFields .= ', stock_movements.total_cost';
        }

        return view('panel/inventory/index', [
            'title' => 'Penerimaan dan kartu stok',
            'user' => $user,
            'isOwner' => $isOwner,
            'items' => $items->orderBy('name')->get()->getResultArray(),
            'receipts' => $receipts->orderBy('goods_receipts.id', 'DESC')->limit(200)->get()->getResultArray(),
            'movements' => $database->table('stock_movements')
                ->select($movementFields)
                ->join('inventory_items', 'inventory_items.id = stock_movements.inventory_item_id')
                ->orderBy('posted_at', 'DESC')->orderBy('stock_movements.id', 'DESC')->limit(200)->get()->getResultArray(),
        ]);
    }

    public function receipt(int $receiptId): string
    {
        $database = db_connect();
        $receipt = $database->table('goods_receipts')
            ->select('goods_receipts.*, suppliers.name AS supplier_name, creator.name AS creator_name, submitter.name AS submitter_name, reviewer.name AS reviewer_name, poster.name AS poster_name')
            ->join('suppliers', 'suppliers.id = goods_receipts.supplier_id', 'left')
            ->join('users AS creator', 'creator.id = goods_receipts.created_by')
            ->join('users AS submitter', 'submitter.id = goods_receipts.submitted_by', 'left')
            ->join('users AS reviewer', 'reviewer.id = goods_receipts.reviewed_by', 'left')
            ->join('users AS poster', 'poster.id = goods_receipts.posted_by', 'left')
            ->where('goods_receipts.id', $receiptId)->get()->getRowArray()
            ?? throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();

        $user = session('user');
        if (($user['role'] ?? '') !== 'owner' && (int) $receipt['created_by'] !== (int) session('user_id')) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $lines = $database->table('goods_receipt_items')
            ->select('goods_receipt_items.*, inventory_items.name AS item_name, inventory_items.base_unit')
            ->join('inventory_items', 'inventory_items.id = goods_receipt_items.inventory_item_id')
            ->where('goods_receipt_id', $receiptId)->orderBy('goods_receipt_items.id')->get()->getResultArray();
        $canEdit = (int) $receipt['created_by'] === (int) session('user_id')
            && in_array($receipt['status'], ['draft', 'rejected'], true);

        return view('panel/inventory/receipt', [
            'title' => 'Penerimaan ' . $receipt['reference_number'],
            'user' => $user,
            'receipt' => $receipt,
            'lines' => $lines,
            'items' => $canEdit ? (new InventoryItemModel())->where('is_active', true)->orderBy('name')->findAll() : [],
            'canEdit' => $canEdit,
            'canReview' => ($user['role'] ?? '') === 'owner' && $receipt['status'] === 'submitted',
            'canPost' => $receipt['status'] === 'approved'
                && (($user['role'] ?? '') === 'owner' || (int) $receipt['created_by'] === (int) session('user_id')),
        ]);
    }

    public function newReceipt(): string
    {
        if (!$this->isInventoryStaff()) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('panel/inventory/new_receipt', [
            'title' => 'Buat draft penerimaan',
            'user' => session('user'),
            'suppliers' => (new SupplierModel())->where('is_active', true)->orderBy('name')->findAll(),
        ]);
    }

    public function createReceipt(): RedirectResponse
    {
        if (!$this->isInventoryStaff()) {
            return redirect()->route('panel')->with('error', 'Penerimaan hanya dapat dibuat oleh owner atau kasir.');
        }
        $referenceInput = $this->request->getPost('reference_number');
        $notesInput = $this->request->getPost('notes');
        if (!is_string($referenceInput) || ($notesInput !== null && !is_string($notesInput))) {
            return redirect()->back()->withInput()->with('error', 'Nomor referensi atau catatan tidak valid.');
        }
        $referenceNumber = strtoupper(trim($referenceInput));
        $rules = [
            'reference_number' => 'required|min_length[2]|max_length[80]',
            'supplier_id' => 'permit_empty|is_natural_no_zero',
            'document_date' => 'required|valid_date[Y-m-d]',
            'payment_source' => 'required|in_list[cash,bank,payable]',
            'notes' => 'permit_empty|max_length[500]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        $database = db_connect();
        if ($database->table('goods_receipts')->where('reference_number', $referenceNumber)->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'Nomor referensi sudah digunakan.');
        }
        $supplierId = $this->request->getPost('supplier_id') ? (int) $this->request->getPost('supplier_id') : null;
        if ($supplierId && !(new SupplierModel())->where('is_active', true)->find($supplierId)) {
            return redirect()->back()->withInput()->with('error', 'Supplier aktif tidak ditemukan.');
        }
        try {
            $database->table('goods_receipts')->insert([
            'reference_number' => $referenceNumber,
            'supplier_id' => $supplierId,
            'document_date' => (string) $this->request->getPost('document_date'),
            'payment_source' => (string) $this->request->getPost('payment_source'),
            'status' => 'draft',
            'notes' => trim($notesInput ?? '') ?: null,
            'created_by' => (int) session('user_id'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'Draft penerimaan stok gagal dibuat.');
            return redirect()->back()->withInput()->with('error', 'Draft belum berhasil dibuat. Periksa nomor referensi lalu coba lagi.');
        }
        return redirect()->to(site_url('panel/inventory/receipts/' . $database->insertID()))->with('success', 'Draft penerimaan berhasil dibuat. Tambahkan item bahan yang diterima.');
    }

    public function addReceiptItem(): RedirectResponse
    {
        if (!is_string($this->request->getPost('purchase_unit'))) {
            return redirect()->back()->with('error', 'Satuan pembelian tidak valid.');
        }
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
        $receiptId = (int) $this->request->getPost('receipt_id');
        $itemId = (int) $this->request->getPost('item_id');
        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            $receipt = $database->query('SELECT * FROM goods_receipts WHERE id = ? FOR UPDATE', [$receiptId])->getRowArray();
            if (!$receipt || !$this->canEditReceipt($receipt)) {
                throw new RuntimeException('Draft penerimaan tidak ditemukan atau tidak dapat diubah.');
            }
            $item = $database->table('inventory_items')->where('id', $itemId)->where('is_active', true)->get()->getRowArray();
            if (!$item) {
                throw new RuntimeException('Bahan aktif tidak ditemukan.');
            }
            $purchaseUnit = strtolower(trim((string) $this->request->getPost('purchase_unit')));
            $factor = $purchaseUnit === $item['base_unit'] ? '1.000000' : (string) ($database->table('unit_conversions')->where('inventory_item_id', $itemId)->where('purchase_unit', $purchaseUnit)->get()->getRow('factor_to_base') ?? '0');
            if (Money::factorMillionths($factor) <= 0) {
                throw new RuntimeException('Konversi satuan pembelian belum tersedia untuk bahan tersebut.');
            }
            $purchaseQuantityMilli = Money::quantityMilli((string) $this->request->getPost('purchase_quantity'));
            $purchaseCostScaled = Money::unitCostTenThousand((string) $this->request->getPost('purchase_unit_cost'));
            $baseQuantityMilli = Money::quantityByFactor($purchaseQuantityMilli, $factor);
            if ($purchaseQuantityMilli <= 0 || $baseQuantityMilli <= 0) {
                throw new RuntimeException('Jumlah pembelian terlalu kecil untuk satuan dasar item ini.');
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
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->to(site_url('panel/inventory/receipts/' . $receiptId))->with('error', $this->receiptError($exception));
        }
        return redirect()->to(site_url('panel/inventory/receipts/' . $receiptId))->with('success', 'Item penerimaan disimpan sebagai draft.');
    }

    public function submitReceipt(int $receiptId): RedirectResponse
    {
        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            $receipt = $database->query('SELECT * FROM goods_receipts WHERE id = ? FOR UPDATE', [$receiptId])->getRowArray();
            if (!$receipt || !$this->canEditReceipt($receipt)) {
                throw new RuntimeException('Draft penerimaan tidak ditemukan atau tidak dapat diajukan.');
            }
            if ($database->table('goods_receipt_items')->where('goods_receipt_id', $receiptId)->countAllResults() < 1) {
                throw new RuntimeException('Tambahkan minimal satu item sebelum meminta persetujuan.');
            }
            $submittedAt = date('Y-m-d H:i:s');
            $database->table('goods_receipts')->where('id', $receiptId)->update([
                'status' => 'submitted',
                'submitted_by' => (int) session('user_id'),
                'submitted_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ]);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->to(site_url('panel/inventory/receipts/' . $receiptId))->with('error', $this->receiptError($exception));
        }

        return redirect()->to(site_url('panel/inventory/receipts/' . $receiptId))->with('success', 'Penerimaan diajukan untuk persetujuan owner. Stok belum berubah.');
    }

    public function approveReceipt(int $receiptId): RedirectResponse
    {
        if (!$this->isOwner()) {
            return redirect()->route('panel')->with('error', 'Persetujuan penerimaan hanya dapat dilakukan owner.');
        }

        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            $receipt = $database->query('SELECT * FROM goods_receipts WHERE id = ? FOR UPDATE', [$receiptId])->getRowArray();
            if (!$receipt || $receipt['status'] !== 'submitted') {
                throw new RuntimeException('Penerimaan tidak sedang menunggu persetujuan.');
            }
            if ($database->table('goods_receipt_items')->where('goods_receipt_id', $receiptId)->countAllResults() < 1) {
                throw new RuntimeException('Penerimaan tidak memiliki item untuk disetujui.');
            }
            $reviewedAt = date('Y-m-d H:i:s');
            $database->table('goods_receipts')->where('id', $receiptId)->update([
                'status' => 'approved',
                'reviewed_by' => (int) session('user_id'),
                'reviewed_at' => $reviewedAt,
                'review_notes' => null,
                'updated_at' => $reviewedAt,
            ]);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->to(site_url('panel/inventory/receipts/' . $receiptId))->with('error', $this->receiptError($exception));
        }

        return redirect()->to(site_url('panel/inventory/receipts/' . $receiptId))->with('success', 'Biaya disetujui. Kasir dapat mem-posting penerimaan.');
    }

    public function rejectReceipt(int $receiptId): RedirectResponse
    {
        if (!$this->isOwner()) {
            return redirect()->route('panel')->with('error', 'Persetujuan penerimaan hanya dapat dilakukan owner.');
        }
        $reviewNotesInput = $this->request->getPost('review_notes');
        $reviewNotes = is_string($reviewNotesInput) ? trim($reviewNotesInput) : '';
        if (mb_strlen($reviewNotes) < 5 || mb_strlen($reviewNotes) > 500) {
            return redirect()->to(site_url('panel/inventory/receipts/' . $receiptId))->withInput()->with('error', 'Catatan perbaikan wajib diisi antara 5 dan 500 karakter.');
        }

        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            $receipt = $database->query('SELECT * FROM goods_receipts WHERE id = ? FOR UPDATE', [$receiptId])->getRowArray();
            if (!$receipt || $receipt['status'] !== 'submitted') {
                throw new RuntimeException('Penerimaan tidak sedang menunggu persetujuan.');
            }
            $reviewedAt = date('Y-m-d H:i:s');
            $database->table('goods_receipts')->where('id', $receiptId)->update([
                'status' => 'rejected',
                'reviewed_by' => (int) session('user_id'),
                'reviewed_at' => $reviewedAt,
                'review_notes' => $reviewNotes,
                'updated_at' => $reviewedAt,
            ]);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->to(site_url('panel/inventory/receipts/' . $receiptId))->with('error', $this->receiptError($exception));
        }

        return redirect()->to(site_url('panel/inventory/receipts/' . $receiptId))->with('success', 'Penerimaan dikembalikan ke kasir. Stok dan jurnal tidak berubah.');
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
            if (!$receipt || !$this->canPostReceipt($receipt)) {
                throw new RuntimeException('Penerimaan belum disetujui owner atau tidak dapat diposting oleh akun ini.');
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
            return redirect()->back()->with('error', $this->receiptError($exception));
        }
        return redirect()->route('panel.inventory')->with('success', 'Penerimaan diposting dan saldo stok diperbarui.');
    }

    private function isOwner(): bool
    {
        return (session('user')['role'] ?? '') === 'owner';
    }

    private function isInventoryStaff(): bool
    {
        return in_array(session('user')['role'] ?? null, ['owner', 'cashier'], true);
    }

    private function canEditReceipt(array $receipt): bool
    {
        return $this->isInventoryStaff()
            && (int) $receipt['created_by'] === (int) session('user_id')
            && in_array($receipt['status'], ['draft', 'rejected'], true);
    }

    private function canPostReceipt(array $receipt): bool
    {
        if ($receipt['status'] !== 'approved' || !$this->isInventoryStaff()) {
            return false;
        }

        return $this->isOwner() || (int) $receipt['created_by'] === (int) session('user_id');
    }

    private function receiptError(\Throwable $exception): string
    {
        if ($exception instanceof \CodeIgniter\Database\Exceptions\DatabaseException) {
            log_message('error', 'Penerimaan stok gagal diproses.');
            return 'Penerimaan belum berhasil diproses. Periksa data dan coba lagi.';
        }
        if ($exception instanceof RuntimeException || $exception instanceof \InvalidArgumentException) {
            return $exception->getMessage();
        }

        log_message('error', 'Penerimaan stok gagal diproses.');
        return 'Penerimaan belum berhasil diproses. Periksa data dan coba lagi.';
    }
}
