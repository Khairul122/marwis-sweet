<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Services\AccountingService;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

class ShiftReviewController extends BaseController
{
    public function index(): string
    {
        $database = db_connect();
        $shifts = $database->table('shifts')
            ->select('shifts.*, users.name AS cashier_name')
            ->join('users', 'users.id = shifts.cashier_id')
            ->where('shifts.status', 'closed')
            ->where('shifts.cash_review_status', 'pending')
            ->orderBy('shifts.closed_at', 'ASC')
            ->get()->getResultArray();
        $recentReviews = $database->table('shifts')
            ->select('shifts.*, users.name AS cashier_name, reviewers.name AS reviewer_name')
            ->join('users', 'users.id = shifts.cashier_id')
            ->join('users AS reviewers', 'reviewers.id = shifts.cash_reviewed_by', 'left')
            ->whereIn('shifts.cash_review_status', ['approved', 'rejected'])
            ->orderBy('shifts.cash_reviewed_at', 'DESC')
            ->limit(30)
            ->get()->getResultArray();

        return view('panel/shift_reviews/index', [
            'title' => 'Review selisih shift',
            'user' => session('user'),
            'shifts' => $shifts,
            'recentReviews' => $recentReviews,
        ]);
    }

    public function decide(int $shiftId, string $decision): RedirectResponse
    {
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return redirect()->route('panel.shift-reviews')->with('error', 'Keputusan review tidak valid.');
        }
        if (!$this->validate(['reason' => 'required|min_length[3]|max_length[500]'])) {
            return redirect()->route('panel.shift-reviews')->with('error', implode(' ', $this->validator->getErrors()));
        }

        $database = db_connect();
        $ownerId = (int) session('user_id');
        $reason = trim((string) $this->request->getPost('reason'));
        if (mb_strlen($reason) < 3) {
            return redirect()->route('panel.shift-reviews')->with('error', 'Alasan review minimal 3 karakter.');
        }

        try {
            $database->transException(true)->transStart();
            $shift = $database->query('SELECT * FROM shifts WHERE id = ? FOR UPDATE', [$shiftId])->getRowArray();
            if (!$shift || $shift['status'] !== 'closed' || $shift['cash_review_status'] !== 'pending') {
                throw new RuntimeException('Shift tidak ditemukan atau sudah memiliki keputusan review.');
            }
            $difference = (float) $shift['cash_difference'];
            if (abs($difference) < 0.01) {
                throw new RuntimeException('Shift tanpa selisih tidak memerlukan review.');
            }

            if ($decision === 'approve') {
                if (empty($shift['closed_at'])) {
                    throw new RuntimeException('Waktu penutupan shift tidak tersedia untuk jurnal penyesuaian.');
                }
                $description = 'Penyesuaian selisih kas shift ' . $shift['shift_number'];
                $lines = $difference < 0
                    ? [
                        ['account_key' => 'cash_variance', 'debit' => abs($difference), 'credit' => 0],
                        ['account_key' => 'cash_drawer', 'debit' => 0, 'credit' => abs($difference)],
                    ]
                    : [
                        ['account_key' => 'cash_drawer', 'debit' => $difference, 'credit' => 0],
                        ['account_key' => 'cash_variance', 'debit' => 0, 'credit' => $difference],
                    ];
                (new AccountingService($database))->post(
                    'shift_cash_review',
                    $shiftId,
                    date('Y-m-d', strtotime((string) $shift['closed_at'])),
                    $description,
                    $lines,
                    $ownerId,
                );
            }

            $database->table('shifts')->where('id', $shiftId)->update([
                'cash_review_status' => $decision === 'approve' ? 'approved' : 'rejected',
                'cash_reviewed_by' => $ownerId,
                'cash_reviewed_at' => date('Y-m-d H:i:s'),
                'cash_review_reason' => $reason,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $database->transComplete();
            if (!$database->transStatus()) {
                throw new RuntimeException('Keputusan review belum berhasil disimpan.');
            }
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->route('panel.shift-reviews')->with('error', $exception->getMessage());
        }

        $message = $decision === 'approve'
            ? 'Selisih disetujui dan jurnal penyesuaian telah diposting.'
            : 'Selisih ditolak; jurnal penyesuaian tidak dibuat.';
        return redirect()->route('panel.shift-reviews')->with('success', $message);
    }
}
