<?php

use App\Services\SalesReportService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/** @internal */
final class SalesReportPaginationWorkflowTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private $database;
    private int $ownerId;
    private int $cashierId;
    private int $categoryId;
    private int $productId;
    private int $variantId;
    private int $shiftId;
    private array $saleIds = [];
    private array $refundIds = [];
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $filters = config('Filters');
        $filters->globals['before'] = array_values(array_filter(
            $filters->globals['before'],
            static fn (string $filter): bool => $filter !== 'csrf',
        ));
        $this->database = db_connect();
        $suffix = strtolower(bin2hex(random_bytes(5)));
        $now = date('Y-m-d H:i:s');
        $this->date = date('Y-m-d');

        foreach ([['owner', 'owner'], ['cashier', 'cashier']] as [$name, $role]) {
            $this->database->table('users')->insert([
                'name' => 'Report ' . ucfirst($name), 'username' => 'r23' . $name . $suffix,
                'email' => null, 'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'role' => $role, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($role === 'owner') { $this->ownerId = (int) $this->database->insertID(); }
            else { $this->cashierId = (int) $this->database->insertID(); }
        }

        $this->database->table('categories')->insert(['name' => 'Report test ' . $suffix, 'slug' => 'report-test-' . $suffix, 'description' => null, 'sort_order' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $this->categoryId = (int) $this->database->insertID();
        $this->database->table('products')->insert(['category_id' => $this->categoryId, 'name' => 'Produk laporan', 'slug' => 'report-product-' . $suffix, 'description' => null, 'product_type' => 'prepared', 'image_path' => null, 'is_featured' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $this->productId = (int) $this->database->insertID();
        $this->database->table('product_variants')->insert(['product_id' => $this->productId, 'name' => 'Satu', 'sku' => 'R23-' . strtoupper($suffix), 'price' => '1000.00', 'stock_mode' => 'none', 'is_default' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $this->variantId = (int) $this->database->insertID();
        $this->database->table('shifts')->insert(['shift_number' => 'R23-' . strtoupper($suffix), 'cashier_id' => $this->cashierId, 'opened_at' => $now, 'opening_cash' => '0.00', 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]);
        $this->shiftId = (int) $this->database->insertID();

        for ($index = 1; $index <= 155; $index++) {
            $this->database->table('sales')->insert([
                'sale_number' => 'R23-' . strtoupper($suffix) . '-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'idempotency_key' => hash('sha256', $suffix . '-' . $index), 'shift_id' => $this->shiftId,
                'cashier_id' => $this->cashierId, 'status' => 'paid', 'subtotal' => '1000.00', 'discount' => '0.00',
                'tax' => '0.00', 'service_charge' => '0.00', 'total' => '1000.00', 'total_cogs' => '0.00',
                'paid_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $saleId = (int) $this->database->insertID();
            $this->saleIds[] = $saleId;
            $this->database->table('sale_items')->insert([
                'sale_id' => $saleId, 'product_id' => $this->productId, 'product_variant_id' => $this->variantId,
                'product_name' => 'Produk laporan', 'variant_name' => 'Satu', 'sku' => 'R23-' . strtoupper($suffix),
                'quantity' => '1.000', 'unit_price' => '1000.00', 'line_total' => '1000.00', 'unit_cogs' => '0.0000',
                'line_cogs' => '0.00', 'recipe_snapshot' => null, 'topping_snapshot' => '[]', 'created_at' => $now,
            ]);
            $saleItemId = (int) $this->database->insertID();
            $this->database->table('payments')->insert(['sale_id' => $saleId, 'method' => $index % 2 === 0 ? 'cash' : 'qris', 'amount' => '1000.00', 'cash_received' => null, 'change_amount' => '0.00', 'created_at' => $now]);

            if ($index <= 152) {
                $this->database->table('refunds')->insert([
                    'refund_number' => 'RF-R23-' . strtoupper($suffix) . '-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                    'sale_id' => $saleId, 'shift_id' => $this->shiftId, 'status' => 'posted', 'method' => 'cash',
                    'subtotal_amount' => '100.00', 'discount_amount' => '0.00', 'tax_amount' => '0.00', 'service_charge_amount' => '0.00',
                    'amount' => '100.00', 'reason' => 'Uji paginasi laporan', 'requested_by' => $this->ownerId,
                    'approved_by' => $this->ownerId, 'submitted_at' => $now, 'posted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $refundId = (int) $this->database->insertID();
                $this->refundIds[] = $refundId;
                $this->database->table('refund_items')->insert([
                    'refund_id' => $refundId, 'sale_item_id' => $saleItemId, 'quantity' => '0.100',
                    'gross_amount' => '100.00', 'discount_amount' => '0.00', 'tax_amount' => '0.00', 'service_charge_amount' => '0.00',
                    'refund_amount' => '100.00', 'restock_decision' => 'not_returned', 'cogs_reversed' => '0.00', 'created_at' => $now,
                ]);
            }
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->database, $this->saleIds) && $this->saleIds !== []) {
            $refundIds = array_column($this->database->table('refunds')->select('id')->whereIn('sale_id', $this->saleIds)->get()->getResultArray(), 'id');
            if ($refundIds !== []) { $this->database->table('refund_items')->whereIn('refund_id', $refundIds)->delete(); }
            $this->database->table('refunds')->whereIn('sale_id', $this->saleIds)->delete();
            $this->database->table('payments')->whereIn('sale_id', $this->saleIds)->delete();
            $this->database->table('sale_items')->whereIn('sale_id', $this->saleIds)->delete();
            $this->database->table('sales')->whereIn('id', $this->saleIds)->delete();
            $this->database->table('shifts')->where('id', $this->shiftId)->delete();
            $this->database->table('products')->where('id', $this->productId)->delete();
            $this->database->table('categories')->where('id', $this->categoryId)->delete();
            $this->database->table('users')->whereIn('id', [$this->ownerId, $this->cashierId])->delete();
        }
        parent::tearDown();
    }

    public function testPagedDetailsKeepCompleteTotalsAndFilterWithoutMaterializingSaleIds(): void
    {
        $service = new SalesReportService($this->database);
        $input = ['period_type' => 'daily', 'date' => $this->date, 'sales_page' => 2, 'refund_page' => 2];
        $report = $service->build($input, true);

        self::assertSame(155, $report['salesTotal']);
        self::assertSame(2, $report['salesPage']);
        self::assertCount(50, $report['sales']);
        self::assertSame(152, $report['refundsTotal']);
        self::assertSame(2, $report['refundPage']);
        self::assertCount(50, $report['refunds']);
        self::assertSame('155000.00', $report['totals']['gross']);
        self::assertSame('139800.00', $report['totals']['net_sales']);
        self::assertSame('15200.00', $report['totals']['refunds']);
        self::assertSame('77000.00', $report['paymentTotals']['cash']);
        self::assertSame('78000.00', $report['paymentTotals']['qris']);
        self::assertSame('155.000', $report['totals']['units']);
        $aggregate = $this->database->table('sales')->selectSum('subtotal', 'gross')->whereIn('id', $this->saleIds)->get()->getRow('gross');
        self::assertSame(number_format((float) $aggregate, 2, '.', ''), $report['totals']['gross']);

        $filtered = $service->build($input + ['product' => $this->productId, 'sale_method' => 'cash'], true);
        self::assertSame(77, $filtered['salesTotal']);
        self::assertSame('77000.00', $filtered['totals']['gross']);

        $pdfReport = $service->build($input, true, 3, true);
        self::assertCount(3, $pdfReport['sales']);
        self::assertCount(3, $pdfReport['refunds']);
        self::assertTrue($pdfReport['salesDetailsTruncated']);
        self::assertTrue($pdfReport['refundDetailsTruncated']);
        self::assertSame($report['totals'], $pdfReport['totals']);

        $page = $this->withSession(['user_id' => $this->ownerId])->get('panel/reports?period_type=daily&date=' . $this->date . '&sales_page=2&refund_page=2');
        $page->assertOK();
        $page->assertSee('Menampilkan 51–100 dari 155 transaksi');
        $page->assertSee('Menampilkan 51–100 dari 152 refund');
        $page->assertSee('Halaman detail transaksi');
        $page->assertSee('Halaman refund');

        $pdf = $this->withSession(['user_id' => $this->ownerId])->get('panel/reports/sales.pdf?period_type=daily&date=' . $this->date);
        $pdf->assertOK();
        self::assertSame('application/pdf', $pdf->response()->getHeaderLine('Content-Type'));
        $pdfBytes = (string) $pdf->response()->getBody();
        self::assertStringStartsWith('%PDF-', $pdfBytes);
        self::assertGreaterThanOrEqual(2, preg_match_all('/\/Type\s*\/Page\b/', $pdfBytes), 'Laporan detail yang panjang harus menghasilkan PDF multi-halaman.');
    }
}
