<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class OperationalReportService
{
    private const PDF_DETAIL_LIMIT = 5000;

    public function __construct(private ?BaseConnection $database = null) { $this->database ??= db_connect(); }

    public function build(string $type, array $input, string $role, int $userId): array
    {
        if (!in_array($type, ['stock', 'opnames', 'expenses', 'shifts', 'finance'], true)) { throw new RuntimeException('Jenis laporan tidak valid.'); }
        if (in_array($type, ['expenses', 'finance'], true) && $role !== 'owner') { throw new RuntimeException('Laporan ini hanya tersedia untuk owner.'); }
        $period = (new SalesReportService($this->database))->period($input);
        $includeFinancials = $role === 'owner';
        $data = match ($type) {
            'stock' => $this->stock($period, $includeFinancials), 'opnames' => $this->opnames($period, $includeFinancials), 'expenses' => $this->expenses($period),
            'shifts' => $this->shifts($period, $role, $userId), 'finance' => $this->finance($period),
        };
        return ['type' => $type, 'period' => $period, 'data' => $data, 'includeFinancials' => $includeFinancials];
    }

    private function stock(array $period, bool $includeFinancials): array
    {
        $movementCount = $this->database->table('stock_movements')->where('posted_at >=', $period['start'])->where('posted_at <', $period['end'])->countAllResults();
        $this->assertPdfDetailLimit($movementCount);
        $valueColumn = $includeFinancials
            ? ', COALESCE(SUM(CASE WHEN m.posted_at < ? THEN m.total_cost ELSE 0 END),0) AS value'
            : '';
        $bindings = $includeFinancials
            ? [$period['end'], $period['end']]
            : [$period['end']];
        $position = $this->database->query(
            'SELECT i.name, i.sku, i.base_unit, COALESCE(SUM(CASE WHEN m.posted_at < ? THEN m.quantity ELSE 0 END),0) AS quantity'
                . $valueColumn
                . ' FROM inventory_items i LEFT JOIN stock_movements m ON m.inventory_item_id=i.id GROUP BY i.id,i.name,i.sku,i.base_unit ORDER BY i.name',
            $bindings,
        )->getResultArray();

        $movementFields = $includeFinancials
            ? 'stock_movements.*, inventory_items.name, inventory_items.sku, inventory_items.base_unit'
            : 'stock_movements.id, stock_movements.quantity, stock_movements.movement_type, stock_movements.source_type, stock_movements.source_id, stock_movements.notes, stock_movements.posted_at, inventory_items.name, inventory_items.sku, inventory_items.base_unit';
        $movements = $this->database->table('stock_movements')
            ->select($movementFields)
            ->join('inventory_items', 'inventory_items.id=stock_movements.inventory_item_id')
            ->where('posted_at >=', $period['start'])
            ->where('posted_at <', $period['end'])
            ->orderBy('posted_at')
            ->get()
            ->getResultArray();

        $report = ['position' => $position, 'movements' => $movements];
        if ($includeFinancials) {
            $report['totalValue'] = array_sum(array_map(static fn (array $row): float => (float) $row['value'], $position));
        }

        return $report;
    }

    private function opnames(array $period, bool $includeFinancials): array
    {
        $opnameCount = (int) $this->database->query(
            "SELECT COUNT(DISTINCT o.id) AS total FROM stock_opnames o JOIN stock_opname_items oi ON oi.stock_opname_id=o.id WHERE o.status='approved' AND o.approved_at >= ? AND o.approved_at < ?",
            [$period['start'], $period['end']],
        )->getRow('total');
        $this->assertPdfDetailLimit($opnameCount);
        $varianceColumn = $includeFinancials
            ? ', COALESCE(SUM(ABS(oi.difference_quantity * COALESCE(oi.adjustment_unit_cost,oi.unit_cost_snapshot))),0) AS variance_value'
            : '';
        $rows = $this->database->query(
            "SELECT o.opname_number,o.cutoff_at,o.approved_at,u.name AS creator_name,COUNT(oi.id) AS item_count"
                . $varianceColumn
                . " FROM stock_opnames o JOIN users u ON u.id=o.created_by JOIN stock_opname_items oi ON oi.stock_opname_id=o.id WHERE o.status='approved' AND o.approved_at >= ? AND o.approved_at < ? GROUP BY o.id,o.opname_number,o.cutoff_at,o.approved_at,u.name ORDER BY o.approved_at",
            [$period['start'], $period['end']],
        )->getResultArray();

        $report = ['rows' => $rows];
        if ($includeFinancials) {
            $report['totalVariance'] = array_sum(array_map(static fn (array $row): float => (float) $row['variance_value'], $rows));
        }

        return $report;
    }

    private function expenses(array $period): array
    {
        $expenseCount = $this->database->table('expenses')->where('status', 'paid')->where('posting_date >=', substr($period['start'], 0, 10))->where('posting_date <', substr($period['end'], 0, 10))->countAllResults();
        $this->assertPdfDetailLimit($expenseCount);
        $rows = $this->database->table('expenses')->select('expenses.*, expense_categories.name AS category_name, users.name AS creator_name')->join('expense_categories','expense_categories.id=expenses.expense_category_id')->join('users','users.id=expenses.created_by')->where('expenses.status','paid')->where('expenses.posting_date >=',substr($period['start'],0,10))->where('expenses.posting_date <',substr($period['end'],0,10))->orderBy('expenses.posting_date')->get()->getResultArray();
        $byCategory=[]; foreach($rows as $row){$byCategory[$row['category_name']]=($byCategory[$row['category_name']]??0)+(float)$row['amount'];}
        return ['rows'=>$rows,'byCategory'=>$byCategory,'total'=>array_sum(array_map(static fn(array $row):float=>(float)$row['amount'],$rows))];
    }

    private function shifts(array $period,string $role,int $userId): array
    {
        $builder=$this->database->table('shifts')->select('shifts.*,users.name AS cashier_name')->join('users','users.id=shifts.cashier_id')->where('opened_at >=',$period['start'])->where('opened_at <',$period['end']);
        if($role!=='owner'){$builder->where('cashier_id',$userId);}
        $countBuilder=clone $builder;
        $this->assertPdfDetailLimit($countBuilder->countAllResults());
        $rows=$builder->orderBy('opened_at')->get()->getResultArray();
        return ['rows'=>$rows,'expected'=>array_sum(array_map(static fn(array $row):float=>(float)($row['expected_cash']??0),$rows)),'difference'=>array_sum(array_map(static fn(array $row):float=>(float)($row['cash_difference']??0),$rows))];
    }

    private function finance(array $period): array
    {
        $rows=$this->database->query("SELECT a.code,a.name,a.account_type,a.normal_balance,COALESCE(SUM(CASE WHEN je.status='posted' AND je.entry_date >= ? AND je.entry_date < ? THEN jl.debit ELSE 0 END),0) AS debit,COALESCE(SUM(CASE WHEN je.status='posted' AND je.entry_date >= ? AND je.entry_date < ? THEN jl.credit ELSE 0 END),0) AS credit FROM accounts a LEFT JOIN journal_lines jl ON jl.account_id=a.id LEFT JOIN journal_entries je ON je.id=jl.journal_entry_id GROUP BY a.id,a.code,a.name,a.account_type,a.normal_balance ORDER BY a.code",[substr($period['start'],0,10),substr($period['end'],0,10),substr($period['start'],0,10),substr($period['end'],0,10)])->getResultArray();
        $debit=array_sum(array_map(static fn(array $row):float=>(float)$row['debit'],$rows)); $credit=array_sum(array_map(static fn(array $row):float=>(float)$row['credit'],$rows));
        $profit=0.0; foreach($rows as $row){if($row['account_type']==='revenue')$profit+=(float)$row['credit']-(float)$row['debit'];elseif(in_array($row['account_type'],['contra_revenue','expense'],true))$profit-=(float)$row['debit']-(float)$row['credit'];}
        return ['rows'=>$rows,'debit'=>$debit,'credit'=>$credit,'profit'=>$profit];
    }

    private function assertPdfDetailLimit(int $count): void
    {
        if ($count > self::PDF_DETAIL_LIMIT) {
            throw new RuntimeException('PDF ini berisi ' . number_format($count, 0, ',', '.') . ' baris, melewati batas aman 5.000. Pilih periode yang lebih pendek untuk mengunduhnya.');
        }
    }
}
