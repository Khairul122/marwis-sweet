<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Services\RefundService;
use CodeIgniter\HTTP\RedirectResponse;

class RefundController extends BaseController
{
    public function submit(int $saleId): RedirectResponse
    {
        try {
            (new RefundService())->submit($saleId, [
                'method' => $this->request->getPost('method'),
                'reason' => $this->request->getPost('reason'),
                'reference_number' => $this->request->getPost('reference_number'),
                'quantities' => $this->request->getPost('quantities') ?? [],
                'restock' => $this->request->getPost('restock') ?? [],
            ], (int) session('user_id'));
        } catch (\Throwable $exception) {
            return redirect()->to(site_url('panel/transactions/' . $saleId))->with('error', $exception->getMessage());
        }
        return redirect()->to(site_url('panel/transactions/' . $saleId))->with('success', 'Pengajuan refund dikirim untuk persetujuan owner.');
    }

    public function approve(int $refundId): RedirectResponse
    {
        try {
            $saleId = (new RefundService())->approve($refundId, (int) session('user_id'));
        } catch (\Throwable $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
        return redirect()->to(site_url('panel/transactions/' . $saleId))->with('success', 'Refund disetujui dan dibukukan.');
    }

    public function reject(int $refundId): RedirectResponse
    {
        try {
            $saleId = (new RefundService())->reject($refundId, (string) $this->request->getPost('rejection_reason'), (int) session('user_id'));
        } catch (\Throwable $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
        return redirect()->to(site_url('panel/transactions/' . $saleId))->with('success', 'Pengajuan refund ditolak.');
    }
}
