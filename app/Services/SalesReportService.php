<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class SalesReportService
{
    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    public function build(array $input, bool $includeFinancials): array
    {
        $period = $this->period($input);
        $filters = $this->normalizeFilters($input);
        $matchedSaleIds = $this->matchedSaleIds($filters);

        $salesBuilder = $this->database->table('sales')
            ->select('sales.*, users.name AS cashier_name, shifts.shift_number')
            ->join('users', 'users.id = sales.cashier_id')
            ->join('shifts', 'shifts.id = sales.shift_id')
            ->whereIn('sales.status', ['paid', 'partially_refunded', 'refunded'])
            ->where('sales.paid_at >=', $period['start'])
            ->where('sales.paid_at <', $period['end'])
            ->orderBy('sales.paid_at', 'DESC');
        $this->applyDirectSaleFilters($salesBuilder, $filters);
        if ($matchedSaleIds !== null) {
            if ($matchedSaleIds === []) { $sales = []; }
            else { $sales = $salesBuilder->whereIn('sales.id', $matchedSaleIds)->get()->getResultArray(); }
        } else {
            $sales = $salesBuilder->get()->getResultArray();
        }
        $saleIds = array_map('intval', array_column($sales, 'id'));

        $items = [];
        $payments = [];
        if ($saleIds !== []) {
            $itemBuilder = $this->database->table('sale_items')->select('sale_items.*')->whereIn('sale_id', $saleIds);
            if ($filters['product_id']) { $itemBuilder->where('product_id', $filters['product_id']); }
            if ($filters['category_id']) { $itemBuilder->join('products', 'products.id = sale_items.product_id')->where('products.category_id', $filters['category_id']); }
            $items = $itemBuilder->get()->getResultArray();
            $paymentBuilder = $this->database->table('payments')->whereIn('sale_id', $saleIds);
            if ($filters['method']) { $paymentBuilder->where('method', $filters['method']); }
            $payments = $paymentBuilder->get()->getResultArray();
        }

        $refundBuilder = $this->database->table('refunds')
            ->select('refunds.*, sales.sale_number, users.name AS cashier_name, shifts.shift_number')
            ->join('sales', 'sales.id = refunds.sale_id')
            ->join('users', 'users.id = sales.cashier_id')
            ->join('shifts', 'shifts.id = sales.shift_id')
            ->where('refunds.status', 'posted')
            ->where('refunds.posted_at >=', $period['start'])
            ->where('refunds.posted_at <', $period['end'])
            ->orderBy('refunds.posted_at', 'DESC');
        $this->applyDirectSaleFilters($refundBuilder, $filters, 'sales');
        if ($filters['method']) { $refundBuilder->where('refunds.method', $filters['method']); }
        if ($matchedSaleIds !== null) {
            if ($matchedSaleIds === []) { $refunds = []; }
            else { $refunds = $refundBuilder->whereIn('refunds.sale_id', $matchedSaleIds)->get()->getResultArray(); }
        } else {
            $refunds = $refundBuilder->get()->getResultArray();
        }

        $totals = ['transactions' => count($sales), 'units' => 0.0, 'gross' => 0.0, 'discount' => 0.0, 'refunds' => 0.0, 'net_sales' => 0.0, 'tax' => 0.0, 'service' => 0.0, 'cogs' => 0.0, 'gross_profit' => 0.0];
        foreach ($sales as $sale) {
            $totals['gross'] += (float) $sale['subtotal'];
            $totals['discount'] += (float) $sale['discount'];
            $totals['tax'] += (float) $sale['tax'];
            $totals['service'] += (float) $sale['service_charge'];
            $totals['cogs'] += (float) $sale['total_cogs'];
        }
        foreach ($items as $item) { $totals['units'] += (float) $item['quantity']; }
        foreach ($refunds as $refund) { $totals['refunds'] += (float) $refund['amount']; }
        if ($includeFinancials && $refunds !== []) {
            $totals['cogs'] -= (float) ($this->database->table('refund_items')->selectSum('cogs_reversed', 'total')->whereIn('refund_id', array_column($refunds, 'id'))->get()->getRow('total') ?? 0);
        }
        $salesCollected = array_sum(array_map(static fn (array $sale): float => (float) $sale['total'], $sales));
        $totals['net_sales'] = $salesCollected - $totals['refunds'];
        $netProductRevenue = $totals['gross'] - $totals['discount'] - array_sum(array_map(static fn (array $refund): float => (float) $refund['subtotal_amount'] - (float) $refund['discount_amount'], $refunds));
        $totals['gross_profit'] = $netProductRevenue - $totals['cogs'];

        $paymentTotals = [];
        foreach ($payments as $payment) { $paymentTotals[$payment['method']] = ($paymentTotals[$payment['method']] ?? 0.0) + (float) $payment['amount']; }
        $refundByMethod = [];
        foreach ($refunds as $refund) { $refundByMethod[$refund['method']] = ($refundByMethod[$refund['method']] ?? 0.0) + (float) $refund['amount']; }

        $products = [];
        foreach ($items as $item) {
            $key = (string) $item['product_id'] . ':' . (string) $item['product_variant_id'];
            if (!isset($products[$key])) { $products[$key] = ['product_name' => $item['product_name'], 'variant_name' => $item['variant_name'], 'quantity' => 0.0, 'sales' => 0.0]; }
            $products[$key]['quantity'] += (float) $item['quantity'];
            $products[$key]['sales'] += (float) $item['line_total'];
        }
        usort($products, static fn (array $a, array $b): int => $b['quantity'] <=> $a['quantity']);

        return compact('period', 'filters', 'sales', 'refunds', 'paymentTotals', 'refundByMethod', 'products', 'totals') + ['includeFinancials' => $includeFinancials];
    }

    public function options(): array
    {
        return [
            'cashiers' => $this->database->table('users')->select('id, name')->where('role', 'cashier')->orderBy('name')->get()->getResultArray(),
            'products' => $this->database->table('products')->select('id, name')->orderBy('name')->get()->getResultArray(),
            'categories' => $this->database->table('categories')->select('id, name')->orderBy('name')->get()->getResultArray(),
            'shifts' => $this->database->table('shifts')->select('id, shift_number')->orderBy('id', 'DESC')->limit(100)->get()->getResultArray(),
        ];
    }

    public function period(array $input): array
    {
        $timezone = new DateTimeZone('Asia/Jakarta');
        $type = in_array($input['period_type'] ?? '', ['daily', 'monthly', 'yearly'], true) ? $input['period_type'] : 'daily';
        if ($type === 'daily') {
            $value = (string) ($input['date'] ?? date('Y-m-d'));
            $start = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
            if (!$start || $start->format('Y-m-d') !== $value) { throw new RuntimeException('Tanggal laporan tidak valid.'); }
            $end = $start->modify('+1 day');
            $label = $start->format('d/m/Y');
        } elseif ($type === 'monthly') {
            $value = (string) ($input['month'] ?? date('Y-m'));
            $start = DateTimeImmutable::createFromFormat('!Y-m', $value, $timezone);
            if (!$start || $start->format('Y-m') !== $value) { throw new RuntimeException('Bulan laporan tidak valid.'); }
            $end = $start->modify('+1 month');
            $label = $start->format('m/Y');
        } else {
            $value = (string) ($input['year'] ?? date('Y'));
            $start = DateTimeImmutable::createFromFormat('!Y', $value, $timezone);
            if (!$start || $start->format('Y') !== $value || (int) $value < 2000 || (int) $value > 2100) { throw new RuntimeException('Tahun laporan tidak valid.'); }
            $end = $start->modify('+1 year');
            $label = $start->format('Y');
        }
        return ['type' => $type, 'value' => $value, 'label' => $label, 'start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s')];
    }

    private function normalizeFilters(array $input): array
    {
        $integer = static fn (mixed $value): ?int => filter_var($value, FILTER_VALIDATE_INT) ? (int) $value : null;
        $method = in_array($input['method'] ?? '', ['cash', 'qris', 'bank_transfer', 'debit', 'credit'], true) ? (string) $input['method'] : null;
        return ['cashier_id' => $integer($input['cashier'] ?? null), 'product_id' => $integer($input['product'] ?? null), 'category_id' => $integer($input['category'] ?? null), 'shift_id' => $integer($input['shift'] ?? null), 'method' => $method];
    }

    private function matchedSaleIds(array $filters): ?array
    {
        if (!$filters['product_id'] && !$filters['category_id'] && !$filters['method']) { return null; }
        $builder = $this->database->table('sales')->distinct()->select('sales.id');
        if ($filters['product_id'] || $filters['category_id']) {
            $builder->join('sale_items', 'sale_items.sale_id = sales.id');
            if ($filters['product_id']) { $builder->where('sale_items.product_id', $filters['product_id']); }
            if ($filters['category_id']) { $builder->join('products', 'products.id = sale_items.product_id')->where('products.category_id', $filters['category_id']); }
        }
        if ($filters['method']) { $builder->join('payments', 'payments.sale_id = sales.id')->where('payments.method', $filters['method']); }
        return array_map('intval', array_column($builder->get()->getResultArray(), 'id'));
    }

    private function applyDirectSaleFilters(object $builder, array $filters, string $prefix = 'sales'): void
    {
        if ($filters['cashier_id']) { $builder->where($prefix . '.cashier_id', $filters['cashier_id']); }
        if ($filters['shift_id']) { $builder->where($prefix . '.shift_id', $filters['shift_id']); }
    }
}
