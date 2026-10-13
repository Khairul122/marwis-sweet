<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Services\StockAdjustmentService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

final class StockAdjustmentController extends BaseController
{
    public function index(): string
    {
        $database = db_connect();
        $user = session('user');
        $isOwner = ($user['role'] ?? '') === 'owner';
        $query = $database->table('stock_adjustments')
            ->select('stock_adjustments.*, users.name AS creator_name, (SELECT COALESCE(SUM(ABS(value_delta)), 0) FROM stock_adjustment_items WHERE stock_adjustment_id = stock_adjustments.id) AS total_value', false)
            ->join('users', 'users.id = stock_adjustments.created_by');
        if (!$isOwner) {
            $query->where('stock_adjustments.created_by', (int) session('user_id'));
        }

        return view('panel/inventory_adjustments/index', [
            'title' => 'Penyesuaian stok',
            'user' => $user,
            'adjustments' => $query->orderBy('stock_adjustments.id', 'DESC')->limit(100)->get()->getResultArray(),
            'isOwner' => $isOwner,
        ]);
    }

    public function new(): string
    {
        return view('panel/inventory_adjustments/new', [
            'title' => 'Buat penyesuaian stok',
            'user' => session('user'),
            'accounts' => db_connect()->table('accounts')->where('is_active', true)->whereIn('account_type', ['liability', 'equity'])->orderBy('code')->get()->getResultArray(),
        ]);
    }

    public function show(int $adjustmentId): string
    {
        $database = db_connect();
        $adjustment = $database->table('stock_adjustments')
            ->select('stock_adjustments.*, creator.name AS creator_name, submitter.name AS submitter_name, reviewer.name AS reviewer_name, poster.name AS poster_name, offset_account.code AS offset_account_code, offset_account.name AS offset_account_name, original.reference_number AS original_reference, reversal.reference_number AS reversal_reference')
            ->join('users AS creator', 'creator.id = stock_adjustments.created_by')
            ->join('users AS submitter', 'submitter.id = stock_adjustments.submitted_by', 'left')
            ->join('users AS reviewer', 'reviewer.id = stock_adjustments.reviewed_by', 'left')
            ->join('users AS poster', 'poster.id = stock_adjustments.posted_by', 'left')
            ->join('accounts AS offset_account', 'offset_account.id = stock_adjustments.offset_account_id', 'left')
            ->join('stock_adjustments AS original', 'original.id = stock_adjustments.reversal_of_id', 'left')
            ->join('stock_adjustments AS reversal', 'reversal.reversal_of_id = stock_adjustments.id', 'left')
            ->select('reversal.id AS reversal_id')
            ->where('stock_adjustments.id', $adjustmentId)->get()->getRowArray();
        if (!$adjustment) {
            throw PageNotFoundException::forPageNotFound();
        }
        $user = session('user');
        $isOwner = ($user['role'] ?? '') === 'owner';
        if (!$isOwner && (int) $adjustment['created_by'] !== (int) session('user_id')) {
            throw PageNotFoundException::forPageNotFound();
        }
        $lines = $database->table('stock_adjustment_items')
            ->select('stock_adjustment_items.*, inventory_items.name AS item_name, inventory_items.sku')
            ->join('inventory_items', 'inventory_items.id = stock_adjustment_items.inventory_item_id')
            ->where('stock_adjustment_id', $adjustmentId)->orderBy('stock_adjustment_items.id')->get()->getResultArray();

        return view('panel/inventory_adjustments/show', [
            'title' => 'Penyesuaian ' . $adjustment['reference_number'],
            'user' => $user,
            'adjustment' => $adjustment,
            'lines' => $lines,
            'items' => in_array($adjustment['status'], ['draft', 'rejected'], true)
                ? db_connect()->table('inventory_items')->where('is_active', true)->orderBy('name')->get()->getResultArray()
                : [],
            'canEdit' => in_array($adjustment['status'], ['draft', 'rejected'], true)
                && ($isOwner || (int) $adjustment['created_by'] === (int) session('user_id')),
            'canReview' => $isOwner && $adjustment['status'] === 'submitted',
            'canPost' => $adjustment['status'] === 'approved'
                && ($isOwner || (int) $adjustment['created_by'] === (int) session('user_id')),
            'canReverse' => $isOwner && $adjustment['status'] === 'posted'
                && $adjustment['adjustment_type'] !== 'reversal' && !$adjustment['reversal_reference'],
        ]);
    }

    public function create(): RedirectResponse
    {
        $payload = [
            'adjustment_type' => $this->request->getPost('adjustment_type'),
            'document_date' => $this->request->getPost('document_date'),
            'reason' => $this->request->getPost('reason'),
            'offset_account_id' => $this->request->getPost('offset_account_id'),
        ];
        if (!is_string($payload['adjustment_type']) || !is_string($payload['document_date']) || !is_string($payload['reason'])) {
            return redirect()->back()->withInput()->with('error', 'Data penyesuaian tidak valid.');
        }
        try {
            $adjustmentId = (new StockAdjustmentService())->create($payload, (int) session('user_id'));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'Draft penyesuaian belum berhasil dibuat.');
        }

        return redirect()->to(site_url('panel/inventory/adjustments/' . $adjustmentId))->with('success', 'Draft dibuat. Tambahkan bahan, lalu ajukan untuk ditinjau owner.');
    }

    public function saveLine(): RedirectResponse
    {
        $adjustmentId = filter_var($this->request->getPost('adjustment_id'), FILTER_VALIDATE_INT);
        if (!$adjustmentId) {
            return redirect()->to(site_url('panel/inventory/adjustments'))->with('error', 'Dokumen penyesuaian tidak ditemukan.');
        }
        try {
            (new StockAdjustmentService())->saveLine(
                $adjustmentId,
                ['item_id' => $this->request->getPost('item_id'), 'quantity_delta' => $this->request->getPost('quantity_delta'), 'unit_cost' => $this->request->getPost('unit_cost')],
                (int) session('user_id'),
                (string) (session('user')['role'] ?? ''),
            );
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'Perubahan bahan belum tersimpan.', $adjustmentId);
        }

        return redirect()->to(site_url('panel/inventory/adjustments/' . $adjustmentId))->with('success', 'Bahan tersimpan di draft; stok belum berubah.');
    }

    public function submit(int $adjustmentId): RedirectResponse
    {
        try {
            (new StockAdjustmentService())->submit($adjustmentId, (int) session('user_id'), (string) (session('user')['role'] ?? ''));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'Penyesuaian belum dapat diajukan.', $adjustmentId);
        }
        return redirect()->to(site_url('panel/inventory/adjustments/' . $adjustmentId))->with('success', 'Penyesuaian diajukan. Stok dan jurnal belum berubah.');
    }

    public function approve(int $adjustmentId): RedirectResponse
    {
        try {
            (new StockAdjustmentService())->review($adjustmentId, true, '', (int) session('user_id'), (string) (session('user')['role'] ?? ''));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'Persetujuan belum berhasil.', $adjustmentId);
        }
        return redirect()->to(site_url('panel/inventory/adjustments/' . $adjustmentId))->with('success', 'Penyesuaian disetujui. Posting masih menunggu tindakan owner atau pembuat.');
    }

    public function reject(int $adjustmentId): RedirectResponse
    {
        $reviewNotes = $this->request->getPost('review_notes');
        if (!is_string($reviewNotes)) {
            return redirect()->to(site_url('panel/inventory/adjustments/' . $adjustmentId))->with('error', 'Catatan perbaikan tidak valid.');
        }
        try {
            (new StockAdjustmentService())->review($adjustmentId, false, $reviewNotes, (int) session('user_id'), (string) (session('user')['role'] ?? ''));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'Penyesuaian belum berhasil dikembalikan.', $adjustmentId);
        }
        return redirect()->to(site_url('panel/inventory/adjustments/' . $adjustmentId))->with('success', 'Penyesuaian dikembalikan untuk diperbaiki.');
    }

    public function post(): RedirectResponse
    {
        $adjustmentId = filter_var($this->request->getPost('adjustment_id'), FILTER_VALIDATE_INT);
        if (!$adjustmentId) {
            return redirect()->to(site_url('panel/inventory/adjustments'))->with('error', 'Dokumen penyesuaian tidak ditemukan.');
        }
        try {
            (new StockAdjustmentService())->post($adjustmentId, (int) session('user_id'), (string) (session('user')['role'] ?? ''));
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'Penyesuaian belum dapat diposting.', $adjustmentId);
        }
        return redirect()->to(site_url('panel/inventory/adjustments/' . $adjustmentId))->with('success', 'Stok, kartu mutasi, dan jurnal berhasil diperbarui.');
    }

    public function reverse(int $adjustmentId): RedirectResponse
    {
        $reason = $this->request->getPost('reason');
        if (!is_string($reason)) {
            return redirect()->to(site_url('panel/inventory/adjustments/' . $adjustmentId))->with('error', 'Alasan pembalik tidak valid.');
        }
        try {
            $reversalId = (new StockAdjustmentService())->reverse(
                $adjustmentId,
                $reason,
                (int) session('user_id'),
                (string) (session('user')['role'] ?? ''),
            );
        } catch (\Throwable $exception) {
            return $this->failure($exception, 'Dokumen pembalik belum berhasil dibuat.', $adjustmentId);
        }
        return redirect()->to(site_url('panel/inventory/adjustments/' . $reversalId))->with('success', 'Dokumen pembalik diposting dan ditautkan ke transaksi asal.');
    }

    private function failure(\Throwable $exception, string $fallback, ?int $adjustmentId = null): RedirectResponse
    {
        $message = $exception instanceof RuntimeException ? $exception->getMessage() : $fallback;
        if (!$exception instanceof RuntimeException) {
            log_message('error', 'Operasi penyesuaian stok gagal: {exception}', ['exception' => $exception->getMessage()]);
        }
        $target = $adjustmentId ? site_url('panel/inventory/adjustments/' . $adjustmentId) : site_url('panel/inventory/adjustments');
        return redirect()->to($target)->with('error', $message);
    }
}
