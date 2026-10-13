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

        $itemsBySale = [];
        $paymentsBySale = [];
        if ($saleIds !== []) {
            $itemRows = $this->database->table('sale_items')
                ->select('sale_items.*, products.category_id')
                ->join('products', 'products.id = sale_items.product_id', 'left')
                ->whereIn('sale_items.sale_id', $saleIds)->get()->getResultArray();
            foreach ($itemRows as $item) { $itemsBySale[$item['sale_id']][] = $item; }

            $paymentBuilder = $this->database->table('payments')->whereIn('sale_id', $saleIds);
            if ($filters['sale_method']) { $paymentBuilder->where('method', $filters['sale_method']); }
            foreach ($paymentBuilder->get()->getResultArray() as $payment) { $paymentsBySale[$payment['sale_id']][] = $payment; }
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
        if ($filters['refund_method']) { $refundBuilder->where('refunds.method', $filters['refund_method']); }
        if ($matchedSaleIds !== null) {
            if ($matchedSaleIds === []) { $refunds = []; }
            else { $refunds = $refundBuilder->whereIn('refunds.sale_id', $matchedSaleIds)->get()->getResultArray(); }
        } else {
            $refunds = $refundBuilder->get()->getResultArray();
        }

        $refundItemsByRefund = [];
        $refundIds = array_map('intval', array_column($refunds, 'id'));
        if ($refundIds !== []) {
            $refundItemRows = $this->database->table('refund_items')
                ->select('refund_items.*, sale_items.product_id, products.category_id')
                ->join('sale_items', 'sale_items.id = refund_items.sale_item_id')
                ->join('products', 'products.id = sale_items.product_id', 'left')
                ->whereIn('refund_items.refund_id', $refundIds)->get()->getResultArray();
            foreach ($refundItemRows as $item) {
                if (!$this->itemMatchesFilters($item, $filters)) { continue; }
                $refundItemsByRefund[$item['refund_id']][] = $item;
            }
        }

        $totalsCents = ['gross' => 0, 'discount' => 0, 'tax' => 0, 'service' => 0, 'refunds' => 0, 'net_sales' => 0, 'cogs' => 0, 'gross_profit' => 0];
        $unitsMilli = 0;
        $salesCollectedCents = 0;
        $paymentTotalsCents = [];
        $products = [];
        foreach ($sales as $saleIndex => $sale) {
            $saleItems = $itemsBySale[$sale['id']] ?? [];
            if ($saleItems === []) {
                $grossCents = Money::cents($sale['subtotal']);
                $discountCents = Money::cents($sale['discount']);
                $taxCents = Money::cents($sale['tax']);
                $serviceCents = Money::cents($sale['service_charge']);
                $totalsCents['gross'] += $grossCents;
                $totalsCents['discount'] += $discountCents;
                $totalsCents['tax'] += $taxCents;
                $totalsCents['service'] += $serviceCents;
                $salesCollectedCents += Money::cents($sale['total']);
                if ($includeFinancials) { $totalsCents['cogs'] += Money::cents($sale['total_cogs']); }
                $sales[$saleIndex]['report_subtotal'] = Money::decimal($grossCents);
                $sales[$saleIndex]['report_total'] = Money::decimal(Money::cents($sale['total']));
                foreach ($paymentsBySale[$sale['id']] ?? [] as $payment) {
                    $paymentTotalsCents[$payment['method']] = ($paymentTotalsCents[$payment['method']] ?? 0) + Money::cents($payment['amount']);
                }
                continue;
            }

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
                $legacyTransactionDiscounts = Money::allocateCents($unallocatedDiscount, $afterItemDiscount);
                foreach ($transactionDiscounts as $index => $amount) {
                    $transactionDiscounts[$index] = $amount + $legacyTransactionDiscounts[$index];
                }
            }
            $discountAllocations = [];
            foreach ($itemDiscounts as $index => $amount) {
                $discountAllocations[$index] = $amount + $transactionDiscounts[$index];
            }
            foreach ($grossWeights as $index => $grossCents) {
                if ($discountAllocations[$index] > $grossCents) {
                    throw new RuntimeException('Diskon melebihi nilai baris pada transaksi ' . $sale['sale_number'] . '.');
                }
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
                $legacyService = Money::allocateCents($missingService, $afterDiscountWeights);
                foreach ($serviceAllocations as $index => $amount) {
                    $serviceAllocations[$index] = $amount + $legacyService[$index];
                }
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
                $legacyTax = Money::allocateCents($missingTax, $taxBases);
                foreach ($taxAllocations as $index => $amount) {
                    $taxAllocations[$index] = $amount + $legacyTax[$index];
                }
            }
            $netWeights = [];
            $selectedIndices = [];
            $saleGrossCents = 0;
            $saleTotalCents = 0;
            foreach ($saleItems as $index => $item) {
                $grossCents = $grossWeights[$index];
                $netWeights[$index] = $grossCents - $discountAllocations[$index] + $taxAllocations[$index] + $serviceAllocations[$index];
                if (!$this->itemMatchesFilters($item, $filters)) { continue; }

                $selectedIndices[] = $index;
                $saleGrossCents += $grossCents;
                $saleTotalCents += $netWeights[$index];
                $totalsCents['gross'] += $grossCents;
                $totalsCents['discount'] += $discountAllocations[$index];
                $totalsCents['tax'] += $taxAllocations[$index];
                $totalsCents['service'] += $serviceAllocations[$index];
                $unitsMilli += Money::quantityMilli($item['quantity']);
                if ($includeFinancials) { $totalsCents['cogs'] += Money::cents($item['line_cogs']); }

                $key = (string) $item['product_id'] . ':' . (string) $item['product_variant_id'];
                if (!isset($products[$key])) {
                    $products[$key] = ['product_name' => $item['product_name'], 'variant_name' => $item['variant_name'], 'quantity_milli' => 0, 'sales_cents' => 0];
                }
                $products[$key]['quantity_milli'] += Money::quantityMilli($item['quantity']);
                $products[$key]['sales_cents'] += max(0, $grossCents - $discountAllocations[$index]);
            }

            if (array_sum($netWeights) !== Money::cents($sale['total'])) {
                throw new RuntimeException('Rincian transaksi ' . $sale['sale_number'] . ' tidak cocok dengan total pembayaran tersimpan.');
            }

            $salesCollectedCents += $saleTotalCents;
            $sales[$saleIndex]['report_subtotal'] = Money::decimal($saleGrossCents);
            $sales[$saleIndex]['report_total'] = Money::decimal($saleTotalCents);
            foreach ($paymentsBySale[$sale['id']] ?? [] as $payment) {
                $allocations = Money::allocateCents(Money::cents($payment['amount']), $netWeights);
                foreach ($selectedIndices as $index) {
                    $paymentTotalsCents[$payment['method']] = ($paymentTotalsCents[$payment['method']] ?? 0) + $allocations[$index];
                }
            }
        }

        $refundByMethodCents = [];
        $refundProductRevenueCents = 0;
        $refundedCogsCents = 0;
        $filteredRefunds = [];
        foreach ($refunds as $refund) {
            $refundItems = $refundItemsByRefund[$refund['id']] ?? [];
            if (($filters['product_id'] || $filters['category_id']) && $refundItems === []) { continue; }
            $refundGrossCents = 0;
            $refundDiscountCents = 0;
            $refundTaxCents = 0;
            $refundServiceCents = 0;
            $refundAmountCents = 0;
            $refundCogsCents = 0;
            if ($refundItems === []) {
                $refundGrossCents = Money::cents($refund['subtotal_amount']);
                $refundDiscountCents = Money::cents($refund['discount_amount']);
                $refundTaxCents = Money::cents($refund['tax_amount']);
                $refundServiceCents = Money::cents($refund['service_charge_amount']);
                $refundAmountCents = Money::cents($refund['amount']);
                if ($includeFinancials) {
                    $refundCogsCents = Money::cents($this->database->table('refund_items')->selectSum('cogs_reversed', 'total')->where('refund_id', $refund['id'])->get()->getRow('total') ?? 0);
                }
            } else {
                foreach ($refundItems as $item) {
                    $refundGrossCents += Money::cents($item['gross_amount']);
                    $refundDiscountCents += Money::cents($item['discount_amount']);
                    $refundTaxCents += Money::cents($item['tax_amount']);
                    $refundServiceCents += Money::cents($item['service_charge_amount']);
                    $refundAmountCents += Money::cents($item['refund_amount']);
                    if ($includeFinancials) { $refundCogsCents += Money::cents($item['cogs_reversed']); }
                }
            }
            $refund['report_subtotal_amount'] = Money::decimal($refundGrossCents);
            $refund['report_discount_amount'] = Money::decimal($refundDiscountCents);
            $refund['report_tax_amount'] = Money::decimal($refundTaxCents);
            $refund['report_service_charge_amount'] = Money::decimal($refundServiceCents);
            $refund['report_amount'] = Money::decimal($refundAmountCents);
            $refund['report_cogs_reversed'] = Money::decimal($refundCogsCents);
            $filteredRefunds[] = $refund;

            $totalsCents['refunds'] += $refundAmountCents;
            $refundByMethodCents[$refund['method']] = ($refundByMethodCents[$refund['method']] ?? 0) + $refundAmountCents;
            $refundProductRevenueCents += $refundGrossCents - $refundDiscountCents;
            $refundedCogsCents += $refundCogsCents;
        }

        $totalsCents['net_sales'] = $salesCollectedCents - $totalsCents['refunds'];
        $totalsCents['cogs'] -= $refundedCogsCents;
        $totalsCents['gross_profit'] = $totalsCents['gross'] - $totalsCents['discount'] - $refundProductRevenueCents - $totalsCents['cogs'];
        $totals = ['transactions' => count($sales), 'units' => Money::quantity($unitsMilli)];
        foreach ($totalsCents as $key => $cents) { $totals[$key] = Money::decimal($cents); }
        $paymentTotals = [];
        foreach ($paymentTotalsCents as $method => $cents) { $paymentTotals[$method] = Money::decimal($cents); }
        $refundByMethod = [];
        foreach ($refundByMethodCents as $method => $cents) { $refundByMethod[$method] = Money::decimal($cents); }

        foreach ($products as &$product) {
            $product['quantity'] = Money::quantity($product['quantity_milli']);
            $product['sales'] = Money::decimal($product['sales_cents']);
            unset($product['quantity_milli'], $product['sales_cents']);
        }
        unset($product);
        usort($products, static fn (array $a, array $b): int => Money::quantityMilli($b['quantity']) <=> Money::quantityMilli($a['quantity']));

        return [
            'period' => $period,
            'filters' => $filters,
            'sales' => $sales,
            'refunds' => $filteredRefunds,
            'paymentTotals' => $paymentTotals,
            'refundByMethod' => $refundByMethod,
            'products' => $products,
            'totals' => $totals,
            'includeFinancials' => $includeFinancials,
        ];
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

    private function matchedSaleIds(array $filters): ?array
    {
        if (!$filters['product_id'] && !$filters['category_id'] && !$filters['sale_method']) { return null; }
        $builder = $this->database->table('sales')->distinct()->select('sales.id');
        if ($filters['product_id'] || $filters['category_id']) {
            $builder->join('sale_items', 'sale_items.sale_id = sales.id');
            if ($filters['product_id']) { $builder->where('sale_items.product_id', $filters['product_id']); }
            if ($filters['category_id']) { $builder->join('products', 'products.id = sale_items.product_id')->where('products.category_id', $filters['category_id']); }
        }
        if ($filters['sale_method']) { $builder->join('payments', 'payments.sale_id = sales.id')->where('payments.method', $filters['sale_method']); }
        return array_map('intval', array_column($builder->get()->getResultArray(), 'id'));
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
