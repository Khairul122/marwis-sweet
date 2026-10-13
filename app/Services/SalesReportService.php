<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class SalesReportService
{
    public const PAGE_SIZE = 50;
    public const PDF_DETAIL_LIMIT = 5000;
    private const SUMMARY_BATCH_SIZE = 200;

    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    public function build(array $input, bool $includeFinancials, int $detailLimit = self::PAGE_SIZE, bool $includeAllDetails = false): array
    {
        $period = $this->period($input);
        $filters = $this->normalizeFilters($input);
        $detailLimit = max(1, min($detailLimit, self::PDF_DETAIL_LIMIT));
        $salesTotal = $this->salesQuery($period, $filters)->countAllResults();
        $refundsTotal = $this->refundQuery($period, $filters)->countAllResults();
        $salesPages = max(1, (int) ceil($salesTotal / $detailLimit));
        $refundPages = max(1, (int) ceil($refundsTotal / $detailLimit));
        $salesPage = $includeAllDetails ? 1 : min($salesPages, max(1, (int) ($input['sales_page'] ?? 1)));
        $refundPage = $includeAllDetails ? 1 : min($refundPages, max(1, (int) ($input['refund_page'] ?? 1)));
        $salesOffset = ($salesPage - 1) * $detailLimit;
        $refundOffset = ($refundPage - 1) * $detailLimit;
        $salesPageRows = $this->salesQuery($period, $filters)->get($detailLimit, $salesOffset)->getResultArray();
        $refundPageRows = $this->refundQuery($period, $filters)->get($detailLimit, $refundOffset)->getResultArray();
        $salesPageIds = array_fill_keys(array_map('intval', array_column($salesPageRows, 'id')), true);
        $refundPageIds = array_fill_keys(array_map('intval', array_column($refundPageRows, 'id')), true);

        $state = [
            'totalsCents' => ['gross' => 0, 'discount' => 0, 'tax' => 0, 'service' => 0, 'refunds' => 0, 'net_sales' => 0, 'cogs' => 0, 'gross_profit' => 0],
            'unitsMilli' => 0, 'salesCollectedCents' => 0, 'paymentTotalsCents' => [], 'products' => [], 'sales' => [],
            'refundByMethodCents' => [], 'refundProductRevenueCents' => 0, 'refundedCogsCents' => 0, 'refunds' => [],
        ];

        foreach ($this->salesBatches($period, $filters) as $batch) {
            $saleIds = array_map('intval', array_column($batch, 'id'));
            $itemsBySale = $this->itemsBySale($saleIds);
            $paymentsBySale = $this->paymentsBySale($saleIds, $filters['sale_method']);
            foreach ($batch as $sale) {
                $enriched = $this->accumulateSale($sale, $itemsBySale[(int) $sale['id']] ?? [], $paymentsBySale[(int) $sale['id']] ?? [], $filters, $includeFinancials, $state);
                if (isset($salesPageIds[(int) $sale['id']])) { $state['sales'][(int) $sale['id']] = $enriched; }
            }
        }

        foreach ($this->refundBatches($period, $filters) as $batch) {
            $refundIds = array_map('intval', array_column($batch, 'id'));
            $itemsByRefund = $this->itemsByRefund($refundIds);
            foreach ($batch as $refund) {
                $enriched = $this->accumulateRefund($refund, $itemsByRefund[(int) $refund['id']] ?? [], $filters, $includeFinancials, $state);
                if ($enriched !== null && isset($refundPageIds[(int) $refund['id']])) { $state['refunds'][(int) $refund['id']] = $enriched; }
            }
        }

        $totalsCents = $state['totalsCents'];
        $totalsCents['net_sales'] = $state['salesCollectedCents'] - $totalsCents['refunds'];
        $totalsCents['cogs'] -= $state['refundedCogsCents'];
        $totalsCents['gross_profit'] = $totalsCents['gross'] - $totalsCents['discount'] - $state['refundProductRevenueCents'] - $totalsCents['cogs'];
        $totals = ['transactions' => $salesTotal, 'units' => Money::quantity($state['unitsMilli'])];
        foreach ($totalsCents as $key => $cents) { $totals[$key] = Money::decimal($cents); }
        $paymentTotals = array_map(static fn (int $cents): string => Money::decimal($cents), $state['paymentTotalsCents']);
        $refundByMethod = array_map(static fn (int $cents): string => Money::decimal($cents), $state['refundByMethodCents']);
        $products = array_values($state['products']);
        foreach ($products as &$product) {
            $product['quantity'] = Money::quantity($product['quantity_milli']);
            $product['sales'] = Money::decimal($product['sales_cents']);
            unset($product['quantity_milli'], $product['sales_cents']);
        }
        unset($product);
        usort($products, static fn (array $a, array $b): int => Money::quantityMilli($b['quantity']) <=> Money::quantityMilli($a['quantity']));

        return [
            'period' => $period, 'filters' => $filters,
            'sales' => array_values(array_map(static fn (array $row): array => $row, array_intersect_key($state['sales'], $salesPageIds))),
            'refunds' => array_values(array_map(static fn (array $row): array => $row, array_intersect_key($state['refunds'], $refundPageIds))),
            'salesPage' => $salesPage, 'salesPages' => $salesPages, 'salesTotal' => $salesTotal, 'salesPageSize' => $detailLimit,
            'refundPage' => $refundPage, 'refundPages' => $refundPages, 'refundsTotal' => $refundsTotal,
            'salesDetailsTruncated' => $includeAllDetails && $salesTotal > $detailLimit,
            'refundDetailsTruncated' => $includeAllDetails && $refundsTotal > $detailLimit,
            'paymentTotals' => $paymentTotals, 'refundByMethod' => $refundByMethod,
            'products' => $products, 'totals' => $totals, 'includeFinancials' => $includeFinancials,
        ];
    }

    private function salesQuery(array $period, array $filters)
    {
        $builder = $this->database->table('sales')
            ->select('sales.*, users.name AS cashier_name, shifts.shift_number')
            ->join('users', 'users.id = sales.cashier_id')
            ->join('shifts', 'shifts.id = sales.shift_id')
            ->whereIn('sales.status', ['paid', 'partially_refunded', 'refunded'])
            ->where('sales.paid_at >=', $period['start'])
            ->where('sales.paid_at <', $period['end'])
            ->orderBy('sales.paid_at', 'DESC')->orderBy('sales.id', 'DESC');
        $this->applyDirectSaleFilters($builder, $filters);

        if ($filters['product_id'] || $filters['category_id']) {
            $conditions = ['report_items.sale_id = sales.id'];
            if ($filters['product_id']) { $conditions[] = 'report_items.product_id = ' . (int) $filters['product_id']; }
            if ($filters['category_id']) { $conditions[] = 'report_products.category_id = ' . (int) $filters['category_id']; }
            $join = $filters['category_id'] ? ' JOIN products report_products ON report_products.id = report_items.product_id' : '';
            $builder->where('EXISTS (SELECT 1 FROM sale_items report_items' . $join . ' WHERE ' . implode(' AND ', $conditions) . ')', null, false);
        }
        if ($filters['sale_method']) {
            $method = $this->database->escape($filters['sale_method']);
            $builder->where('EXISTS (SELECT 1 FROM payments report_payments WHERE report_payments.sale_id = sales.id AND report_payments.method = ' . $method . ')', null, false);
        }
        return $builder;
    }

    private function refundQuery(array $period, array $filters)
    {
        $builder = $this->database->table('refunds')
            ->select('refunds.*, sales.sale_number, users.name AS cashier_name, shifts.shift_number')
            ->join('sales', 'sales.id = refunds.sale_id')
            ->join('users', 'users.id = sales.cashier_id')
            ->join('shifts', 'shifts.id = sales.shift_id')
            ->where('refunds.status', 'posted')
            ->where('refunds.posted_at >=', $period['start'])
            ->where('refunds.posted_at <', $period['end'])
            ->orderBy('refunds.posted_at', 'DESC')->orderBy('refunds.id', 'DESC');
        $this->applyDirectSaleFilters($builder, $filters, 'sales');
        if ($filters['refund_method']) { $builder->where('refunds.method', $filters['refund_method']); }
        if ($filters['product_id'] || $filters['category_id']) {
            $conditions = ['report_refund_items.refund_id = refunds.id'];
            if ($filters['product_id']) { $conditions[] = 'report_sale_items.product_id = ' . (int) $filters['product_id']; }
            if ($filters['category_id']) { $conditions[] = 'report_products.category_id = ' . (int) $filters['category_id']; }
            $join = ' JOIN sale_items report_sale_items ON report_sale_items.id = report_refund_items.sale_item_id';
            if ($filters['category_id']) { $join .= ' JOIN products report_products ON report_products.id = report_sale_items.product_id'; }
            $builder->where('EXISTS (SELECT 1 FROM refund_items report_refund_items' . $join . ' WHERE ' . implode(' AND ', $conditions) . ')', null, false);
        }
        return $builder;
    }

    private function salesBatches(array $period, array $filters): \Generator
    {
        $lastDate = null;
        $lastId = null;
        do {
            $builder = $this->salesQuery($period, $filters);
            if ($lastDate !== null) {
                $builder->groupStart()->where('sales.paid_at <', $lastDate)->orGroupStart()
                    ->where('sales.paid_at', $lastDate)->where('sales.id <', $lastId)->groupEnd()->groupEnd();
            }
            $batch = $builder->get(self::SUMMARY_BATCH_SIZE)->getResultArray();
            if ($batch === []) { return; }
            yield $batch;
            $last = $batch[array_key_last($batch)];
            $lastDate = $last['paid_at'];
            $lastId = (int) $last['id'];
        } while (count($batch) === self::SUMMARY_BATCH_SIZE);
    }

    private function refundBatches(array $period, array $filters): \Generator
    {
        $lastDate = null;
        $lastId = null;
        do {
            $builder = $this->refundQuery($period, $filters);
            if ($lastDate !== null) {
                $builder->groupStart()->where('refunds.posted_at <', $lastDate)->orGroupStart()
                    ->where('refunds.posted_at', $lastDate)->where('refunds.id <', $lastId)->groupEnd()->groupEnd();
            }
            $batch = $builder->get(self::SUMMARY_BATCH_SIZE)->getResultArray();
            if ($batch === []) { return; }
            yield $batch;
            $last = $batch[array_key_last($batch)];
            $lastDate = $last['posted_at'];
            $lastId = (int) $last['id'];
        } while (count($batch) === self::SUMMARY_BATCH_SIZE);
    }

    private function itemsBySale(array $saleIds): array
    {
        if ($saleIds === []) { return []; }
        $grouped = [];
        $rows = $this->database->table('sale_items')
            ->select('sale_items.*, products.category_id')
            ->join('products', 'products.id = sale_items.product_id', 'left')
            ->whereIn('sale_items.sale_id', $saleIds)->get()->getResultArray();
        foreach ($rows as $row) { $grouped[(int) $row['sale_id']][] = $row; }
        return $grouped;
    }

    private function paymentsBySale(array $saleIds, ?string $method): array
    {
        if ($saleIds === []) { return []; }
        $builder = $this->database->table('payments')->whereIn('sale_id', $saleIds);
        if ($method) { $builder->where('method', $method); }
        $grouped = [];
        foreach ($builder->get()->getResultArray() as $row) { $grouped[(int) $row['sale_id']][] = $row; }
        return $grouped;
    }

    private function itemsByRefund(array $refundIds): array
    {
        if ($refundIds === []) { return []; }
        $grouped = [];
        $rows = $this->database->table('refund_items')
            ->select('refund_items.*, sale_items.product_id, products.category_id')
            ->join('sale_items', 'sale_items.id = refund_items.sale_item_id')
            ->join('products', 'products.id = sale_items.product_id', 'left')
            ->whereIn('refund_items.refund_id', $refundIds)->get()->getResultArray();
        foreach ($rows as $row) { $grouped[(int) $row['refund_id']][] = $row; }
        return $grouped;
    }

    private function accumulateSale(array $sale, array $saleItems, array $payments, array $filters, bool $includeFinancials, array &$state): array
    {
        $saleGrossCents = 0;
        $saleTotalCents = 0;
        if ($saleItems === []) {
            $saleGrossCents = Money::cents($sale['subtotal']);
            $saleTotalCents = Money::cents($sale['total']);
            $state['totalsCents']['gross'] += $saleGrossCents;
            $state['totalsCents']['discount'] += Money::cents($sale['discount']);
            $state['totalsCents']['tax'] += Money::cents($sale['tax']);
            $state['totalsCents']['service'] += Money::cents($sale['service_charge']);
            if ($includeFinancials) { $state['totalsCents']['cogs'] += Money::cents($sale['total_cogs']); }
            foreach ($payments as $payment) {
                $state['paymentTotalsCents'][$payment['method']] = ($state['paymentTotalsCents'][$payment['method']] ?? 0) + Money::cents($payment['amount']);
            }
        } else {
            $grossWeights = array_map(static fn (array $item): int => Money::cents($item['line_total']), $saleItems);
            $itemDiscounts = array_map(static fn (array $item): int => Money::cents($item['item_discount_amount'] ?? 0), $saleItems);
            $transactionDiscounts = array_map(static fn (array $item): int => Money::cents($item['transaction_discount_amount'] ?? 0), $saleItems);
            $headerDiscountCents = Money::cents($sale['discount']);
            if ($headerDiscountCents < 0 || $headerDiscountCents > array_sum($grossWeights)
                || array_sum($itemDiscounts) + array_sum($transactionDiscounts) > $headerDiscountCents) {
                throw new RuntimeException('Alokasi diskon pada transaksi ' . $sale['sale_number'] . ' tidak sesuai dengan total tersimpan.');
            }
            foreach ($grossWeights as $index => $grossCents) {
                if ($itemDiscounts[$index] < 0 || $itemDiscounts[$index] > $grossCents || $transactionDiscounts[$index] < 0) {
                    throw new RuntimeException('Diskon per baris pada transaksi ' . $sale['sale_number'] . ' tidak valid.');
                }
            }
            $afterItemDiscount = array_map(static fn (int $index, int $gross): int => $gross - $itemDiscounts[$index], array_keys($grossWeights), $grossWeights);
            $unallocatedDiscount = $headerDiscountCents - array_sum($itemDiscounts) - array_sum($transactionDiscounts);
            if ($unallocatedDiscount > 0) {
                $legacyDiscounts = Money::allocateRupiahCents($unallocatedDiscount, $afterItemDiscount);
                foreach ($transactionDiscounts as $index => $amount) { $transactionDiscounts[$index] = $amount + $legacyDiscounts[$index]; }
            }
            $discountAllocations = [];
            foreach ($itemDiscounts as $index => $amount) { $discountAllocations[$index] = $amount + $transactionDiscounts[$index]; }
            foreach ($grossWeights as $index => $grossCents) {
                if ($discountAllocations[$index] > $grossCents) { throw new RuntimeException('Diskon melebihi nilai baris pada transaksi ' . $sale['sale_number'] . '.'); }
            }
            $afterDiscountWeights = array_map(static fn (int $index, int $gross): int => $gross - $discountAllocations[$index], array_keys($grossWeights), $grossWeights);
            $serviceAllocations = array_map(static fn (array $item): int => Money::cents($item['service_charge_amount'] ?? 0), $saleItems);
            $headerServiceCents = Money::cents($sale['service_charge']);
            if ($headerServiceCents < 0 || array_sum($serviceAllocations) < 0 || array_sum($serviceAllocations) > $headerServiceCents
                || $headerServiceCents > array_sum($afterDiscountWeights)) {
                throw new RuntimeException('Alokasi biaya layanan pada transaksi ' . $sale['sale_number'] . ' tidak valid.');
            }
            $missingService = $headerServiceCents - array_sum($serviceAllocations);
            if ($missingService > 0) {
                $legacyService = Money::allocateRupiahCents($missingService, $afterDiscountWeights);
                foreach ($serviceAllocations as $index => $amount) { $serviceAllocations[$index] = $amount + $legacyService[$index]; }
            }
            $taxAllocations = array_map(static fn (array $item): int => Money::cents($item['tax_amount'] ?? 0), $saleItems);
            $taxBases = array_map(static fn (int $index, int $net): int => $net + $serviceAllocations[$index], array_keys($afterDiscountWeights), $afterDiscountWeights);
            $headerTaxCents = Money::cents($sale['tax']);
            if ($headerTaxCents < 0 || array_sum($taxAllocations) < 0 || array_sum($taxAllocations) > $headerTaxCents
                || $headerTaxCents > array_sum($taxBases)) {
                throw new RuntimeException('Alokasi pajak pada transaksi ' . $sale['sale_number'] . ' tidak valid.');
            }
            $missingTax = $headerTaxCents - array_sum($taxAllocations);
            if ($missingTax > 0) {
                $legacyTax = Money::allocateRupiahCents($missingTax, $taxBases);
                foreach ($taxAllocations as $index => $amount) { $taxAllocations[$index] = $amount + $legacyTax[$index]; }
            }

            $netWeights = [];
            $selectedIndices = [];
            foreach ($saleItems as $index => $item) {
                $grossCents = $grossWeights[$index];
                $netWeights[$index] = $grossCents - $discountAllocations[$index] + $taxAllocations[$index] + $serviceAllocations[$index];
                if (!$this->itemMatchesFilters($item, $filters)) { continue; }
                $selectedIndices[] = $index;
                $saleGrossCents += $grossCents;
                $saleTotalCents += $netWeights[$index];
                $state['totalsCents']['gross'] += $grossCents;
                $state['totalsCents']['discount'] += $discountAllocations[$index];
                $state['totalsCents']['tax'] += $taxAllocations[$index];
                $state['totalsCents']['service'] += $serviceAllocations[$index];
                $state['unitsMilli'] += Money::quantityMilli($item['quantity']);
                if ($includeFinancials) { $state['totalsCents']['cogs'] += Money::cents($item['line_cogs']); }
                $key = (string) $item['product_id'] . ':' . (string) $item['product_variant_id'];
                if (!isset($state['products'][$key])) {
                    $state['products'][$key] = ['product_name' => $item['product_name'], 'variant_name' => $item['variant_name'], 'quantity_milli' => 0, 'sales_cents' => 0];
                }
                $state['products'][$key]['quantity_milli'] += Money::quantityMilli($item['quantity']);
                $state['products'][$key]['sales_cents'] += max(0, $grossCents - $discountAllocations[$index]);
            }
            if (array_sum($netWeights) !== Money::cents($sale['total'])) {
                throw new RuntimeException('Rincian transaksi ' . $sale['sale_number'] . ' tidak cocok dengan total pembayaran tersimpan.');
            }
            foreach ($payments as $payment) {
                $allocations = Money::allocateRupiahCents(Money::cents($payment['amount']), $netWeights);
                foreach ($selectedIndices as $index) {
                    $state['paymentTotalsCents'][$payment['method']] = ($state['paymentTotalsCents'][$payment['method']] ?? 0) + $allocations[$index];
                }
            }
        }

        $state['salesCollectedCents'] += $saleTotalCents;
        $sale['report_subtotal'] = Money::decimal($saleGrossCents);
        $sale['report_total'] = Money::decimal($saleTotalCents);
        return $sale;
    }

    private function accumulateRefund(array $refund, array $refundItems, array $filters, bool $includeFinancials, array &$state): ?array
    {
        $refundItems = array_values(array_filter($refundItems, fn (array $item): bool => $this->itemMatchesFilters($item, $filters)));
        if (($filters['product_id'] || $filters['category_id']) && $refundItems === []) { return null; }
        $gross = $discount = $tax = $service = $amount = $cogs = 0;
        if ($refundItems === []) {
            $gross = Money::cents($refund['subtotal_amount']);
            $discount = Money::cents($refund['discount_amount']);
            $tax = Money::cents($refund['tax_amount']);
            $service = Money::cents($refund['service_charge_amount']);
            $amount = Money::cents($refund['amount']);
        } else {
            foreach ($refundItems as $item) {
                $gross += Money::cents($item['gross_amount']);
                $discount += Money::cents($item['discount_amount']);
                $tax += Money::cents($item['tax_amount']);
                $service += Money::cents($item['service_charge_amount']);
                $amount += Money::cents($item['refund_amount']);
                if ($includeFinancials) { $cogs += Money::cents($item['cogs_reversed']); }
            }
        }
        $refund['report_subtotal_amount'] = Money::decimal($gross);
        $refund['report_discount_amount'] = Money::decimal($discount);
        $refund['report_tax_amount'] = Money::decimal($tax);
        $refund['report_service_charge_amount'] = Money::decimal($service);
        $refund['report_amount'] = Money::decimal($amount);
        $refund['report_cogs_reversed'] = Money::decimal($cogs);
        $state['totalsCents']['refunds'] += $amount;
        $state['refundByMethodCents'][$refund['method']] = ($state['refundByMethodCents'][$refund['method']] ?? 0) + $amount;
        $state['refundProductRevenueCents'] += $gross - $discount;
        $state['refundedCogsCents'] += $cogs;
        return $refund;
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
        $method = static fn (mixed $value): ?string => in_array($value, ['cash', 'qris', 'bank_transfer', 'debit', 'credit'], true) ? (string) $value : null;
        $legacyMethod = $method($input['method'] ?? null);
        return [
            'cashier_id' => $integer($input['cashier'] ?? null),
            'product_id' => $integer($input['product'] ?? null),
            'category_id' => $integer($input['category'] ?? null),
            'shift_id' => $integer($input['shift'] ?? null),
            'sale_method' => $method($input['sale_method'] ?? null) ?? $legacyMethod,
            'refund_method' => $method($input['refund_method'] ?? null),
        ];
    }

    private function itemMatchesFilters(array $item, array $filters): bool
    {
        if ($filters['product_id'] && (int) $item['product_id'] !== $filters['product_id']) { return false; }
        if ($filters['category_id'] && (int) ($item['category_id'] ?? 0) !== $filters['category_id']) { return false; }
        return true;
    }

    private function applyDirectSaleFilters(object $builder, array $filters, string $prefix = 'sales'): void
    {
        if ($filters['cashier_id']) { $builder->where($prefix . '.cashier_id', $filters['cashier_id']); }
        if ($filters['shift_id']) { $builder->where($prefix . '.shift_id', $filters['shift_id']); }
    }
}
