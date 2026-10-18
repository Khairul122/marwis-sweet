<?php

namespace Tests\Support\Database\Seeds;

use App\Services\RefundService;
use CodeIgniter\Database\Seeder;
use LogicException;

class EvaluationRefundSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->db->query('SELECT DATABASE() AS name')->getRow('name') !== 'marwis_sweet_e2e') {
            throw new LogicException('Fixture refund hanya untuk schema E2E.');
        }
        $ownerId = (int) $this->db->table('users')->where('role', 'owner')->where('status', 'active')->orderBy('id')->get()->getRow('id');
        $sales = $this->db->table('sales')->select('sales.*')->join('shifts', 'shifts.id = sales.shift_id')
            ->like('shifts.shift_number', 'EVL-', 'after')->orderBy('sales.id')->limit(51)->get()->getResultArray();
        if (!$ownerId || count($sales) !== 51) {
            throw new LogicException('Fixture membutuhkan EvaluationDataSeeder dan owner aktif.');
        }
        $service = new RefundService($this->db);
        foreach ($sales as $sale) {
            if ($this->db->table('refunds')->where('sale_id', $sale['id'])->countAllResults() > 0) {
                continue;
            }
            $item = $this->db->table('sale_items')->where('sale_id', $sale['id'])->orderBy('id')->get()->getRowArray();
            $refundId = $service->submit((int) $sale['id'], [
                'method' => 'bank_transfer', 'reference_number' => 'E2E-PAGINATION-' . $sale['id'],
                'reason' => 'Fixture paginasi refund dari transaksi evaluasi',
                'quantities' => [$item['id'] => 1],
            ], (int) $sale['cashier_id']);
            $service->approve($refundId, $ownerId);
        }
    }
}
