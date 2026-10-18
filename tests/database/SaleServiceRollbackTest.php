<?php

use App\Services\Money;
use App\Services\SaleService;
use App\Services\SalesPolicyService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class SaleServiceRollbackTest extends CIUnitTestCase
{
    private ?BaseConnection $database = null;
    private array $fixtures = [];
    private ?array $previousPeriod = null;
    private ?int $userId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = db_connect('tests', false);
        self::assertSame('MySQLi', $this->database->DBDriver);
        self::assertSame('marwis_sweet_e2e', $this->database->query('SELECT DATABASE() AS schema_name')->getRow('schema_name'));
        $this->database->transException(true);
    }

    protected function tearDown(): void
    {
        if ($this->database !== null) {
            while ($this->database->transDepth > 0) {
                $this->database->transRollback();
            }
            if ($this->fixtures !== []) {
                $this->database->transBegin();
                try {
                    if ($this->userId !== null) {
                        $this->database->table('stock_movements')->where('created_by', $this->userId)->delete();
                        $this->database->table('journal_entries')->where('created_by', $this->userId)->delete();
                        $this->database->table('sales')->where('cashier_id', $this->userId)->delete();
                    }
                    if ($this->previousPeriod !== null) {
                        $this->database->table('accounting_periods')->where('id', $this->previousPeriod['id'])->update($this->previousPeriod);
                    }
                    foreach (array_reverse($this->fixtures) as [$table, $id]) {
                        $this->database->table($table)->where('id', $id)->delete();
                    }
                    $this->database->transCommit();
                } catch (Throwable $exception) {
                    $this->database->transRollback();
                    throw $exception;
                } finally {
                    $this->database->close();
                }
            } else {
                $this->database->close();
            }
        }
        parent::tearDown();
    }

    public function testClosedPeriodRollsBackSalePaymentsMovementsJournalAndStock(): void
    {
        $suffix = bin2hex(random_bytes(8));
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        self::assertTrue($this->database->transBegin());
        $this->userId = $this->insert('users', [
            'name' => 'Kasir uji rollback', 'username' => 'rollback-' . $suffix,
            'password_hash' => password_hash($suffix, PASSWORD_DEFAULT), 'role' => 'cashier', 'status' => 'active',
        ]);
        $itemId = $this->insert('inventory_items', [
            'name' => 'Stok uji rollback', 'sku' => 'RB-' . $suffix, 'base_unit' => 'pcs',
            'quantity_on_hand' => '5.000', 'average_cost' => '4000.0000', 'inventory_value' => '20000.00',
        ]);
        $categoryId = $this->insert('categories', ['name' => 'Kategori uji rollback', 'slug' => 'rb-' . $suffix]);
        $productId = $this->insert('products', [
            'category_id' => $categoryId, 'name' => 'Produk uji rollback', 'slug' => 'rb-' . $suffix, 'product_type' => 'stocked',
        ]);
        $variantId = $this->insert('product_variants', [
            'product_id' => $productId, 'name' => 'Reguler', 'sku' => 'RB-' . $suffix,
            'price' => '10000.00', 'stock_mode' => 'direct', 'direct_inventory_item_id' => $itemId,
        ]);
        $this->insert('shifts', [
            'shift_number' => 'RB-' . $suffix, 'cashier_id' => $this->userId, 'opened_at' => $now, 'status' => 'open',
        ]);
        $this->previousPeriod = $this->database->table('accounting_periods')
            ->where('period_start', $today)->where('period_end', $today)->get()->getRowArray();
        if ($this->previousPeriod === null) {
            $this->insert('accounting_periods', ['period_start' => $today, 'period_end' => $today, 'status' => 'closed']);
        } else {
            $this->database->table('accounting_periods')->where('id', $this->previousPeriod['id'])->update(['status' => 'closed']);
        }
        // Finalize must own the top-level transaction so its rollback is exercised.
        self::assertTrue($this->database->transCommit());

        $tables = ['sales', 'sale_items', 'payments', 'stock_movements', 'journal_entries', 'journal_lines'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = $this->database->table($table)->countAllResults();
        }
        $beforeStock = $this->database->table('inventory_items')->where('id', $itemId)->get()->getRowArray();
        $policy = (new SalesPolicyService($this->database))->current();
        $service = $policy['service_charge_enabled'] ? SalesPolicyService::chargeCents(1_000_000, $policy['service_charge_rate_hundredths']) : 0;
        $tax = $policy['tax_enabled'] ? SalesPolicyService::chargeCents(1_000_000 + $service, $policy['tax_rate_hundredths']) : 0;
        $payment = Money::decimal(1_000_000 + $service + $tax);
        $idempotencyKey = bin2hex(random_bytes(16));
        $exception = null;
        try {
            (new SaleService($this->database))->finalize([
                'idempotency_key' => $idempotencyKey,
                'cart' => [['variant_id' => $variantId, 'quantity' => 1]],
                'payments' => [['method' => 'cash', 'amount' => $payment, 'cash_received' => $payment]],
            ], $this->userId);
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        foreach ($tables as $table) {
            self::assertSame($before[$table], $this->database->table($table)->countAllResults(), $table . ' berubah setelah posting ditolak.');
        }
        self::assertSame($beforeStock, $this->database->table('inventory_items')->where('id', $itemId)->get()->getRowArray());
        self::assertSame(0, $this->database->table('sales')->where('idempotency_key', $idempotencyKey)->countAllResults());
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('Periode akuntansi untuk tanggal tersebut sudah ditutup.', $exception->getMessage());
        self::assertSame(0, $this->database->transDepth);
    }

    private function insert(string $table, array $values): int
    {
        self::assertTrue($this->database->table($table)->insert($values));
        $id = (int) $this->database->insertID();
        $this->fixtures[] = [$table, $id];

        return $id;
    }
}
