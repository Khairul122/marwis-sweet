<?php

use App\Database\Seeds\DevelopmentSeeder;
use App\Services\Money;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TemporaryDataSeederTest extends CIUnitTestCase
{
    private const INGREDIENTS = [
        'DEMO-TEPUNG' => [25000, 14],
        'DEMO-TELUR' => [240, 2200],
        'DEMO-GULA' => [10000, 18],
        'DEMO-MENTEGA' => [8000, 45],
        'DEMO-KEJU' => [6000, 95],
        'DEMO-COKELAT' => [7000, 55],
    ];

    private ?BaseConnection $database = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = db_connect();
        self::assertSame('MySQLi', $this->database->DBDriver);
        self::assertSame('marwis_sweet_e2e', $this->database->query('SELECT DATABASE() AS schema_name')->getRow('schema_name'));
        self::assertSame('', $this->database->getPrefix());
        self::assertTrue($this->database->transBegin());

        $prefix = 'seed-old-' . bin2hex(random_bytes(6)) . '-';
        foreach (array_keys(self::INGREDIENTS) as $sku) {
            $this->database->table('inventory_items')->where('sku', $sku)->update(['sku' => $prefix . $sku]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->database !== null) {
            while ($this->database->transDepth > 0) {
                $this->database->transRollback();
            }
        }
        parent::tearDown();
    }

    public function testFreshDevelopmentSeedPostsSixOpeningMovementsAndBalancedCapitalJournals(): void
    {
        $this->seedDevelopment();
        $movementTotal = 0;
        $journalDebitTotal = 0;
        $journalCreditTotal = 0;

        foreach (self::INGREDIENTS as $sku => [$quantity, $cost]) {
            $item = $this->database->table('inventory_items')->where('sku', $sku)->get()->getRowArray();
            self::assertNotNull($item);
            $value = $quantity * $cost * 100;
            self::assertSame($quantity * 1000, Money::quantityMilli($item['quantity_on_hand']));
            self::assertSame($cost * 10000, Money::unitCostTenThousand($item['average_cost']));
            self::assertSame($value, Money::cents($item['inventory_value']));

            $movements = $this->database->table('stock_movements')->where([
                'inventory_item_id' => $item['id'], 'movement_type' => 'opening',
                'source_type' => 'development_opening', 'source_id' => $item['id'],
            ])->get()->getResultArray();
            self::assertCount(1, $movements, $sku . ' harus memiliki satu mutasi saldo awal.');
            self::assertSame($quantity * 1000, Money::quantityMilli($movements[0]['quantity']));
            self::assertSame($cost * 10000, Money::unitCostTenThousand($movements[0]['unit_cost']));
            self::assertSame($value, Money::cents($movements[0]['total_cost']));
            $movementTotal += Money::cents($movements[0]['total_cost']);

            $journals = $this->database->table('journal_entries')->where([
                'source_type' => 'development_opening', 'source_id' => $item['id'], 'status' => 'posted',
            ])->get()->getResultArray();
            self::assertCount(1, $journals, $sku . ' harus memiliki satu jurnal saldo awal.');
            $lines = $this->database->table('journal_lines l')->select('a.system_key, l.debit, l.credit')
                ->join('accounts a', 'a.id = l.account_id')->where('l.journal_entry_id', $journals[0]['id'])
                ->orderBy('a.system_key')->get()->getResultArray();
            $normalized = array_map(static fn (array $line): array => [
                'account' => $line['system_key'], 'debit' => Money::cents($line['debit']), 'credit' => Money::cents($line['credit']),
            ], $lines);
            self::assertSame([
                ['account' => 'inventory', 'debit' => $value, 'credit' => 0],
                ['account' => 'owner_capital', 'debit' => 0, 'credit' => $value],
            ], $normalized);
            $journalDebitTotal += array_sum(array_column($normalized, 'debit'));
            $journalCreditTotal += array_sum(array_column($normalized, 'credit'));
        }

        self::assertSame(237_300_000, $movementTotal);
        self::assertSame($movementTotal, $journalDebitTotal);
        self::assertSame($journalDebitTotal, $journalCreditTotal);
    }

    public function testRepeatedSeedPreservesUsedStockAndDoesNotDuplicateOpeningHistory(): void
    {
        $this->seedDevelopment();
        $items = $this->database->table('inventory_items')->whereIn('sku', array_keys(self::INGREDIENTS))->get()->getResultArray();
        self::assertCount(6, $items);
        $itemIds = array_column($items, 'id');
        $flour = $this->database->table('inventory_items')->where('sku', 'DEMO-TEPUNG')->get()->getRowArray();
        $this->database->table('inventory_items')->where('id', $flour['id'])->update([
            'quantity_on_hand' => '24000.000', 'average_cost' => '15.0000', 'inventory_value' => '360000.00',
        ]);
        $beforeStock = $this->stockValues($itemIds);
        $beforeMovements = $this->database->table('stock_movements')->whereIn('inventory_item_id', $itemIds)->orderBy('id')->get()->getResultArray();
        $beforeJournals = $this->openingJournals($itemIds);
        self::assertCount(6, $beforeMovements);
        self::assertCount(6, $beforeJournals);
        $journalIds = array_column($beforeJournals, 'id');
        $beforeLines = $this->database->table('journal_lines')->whereIn('journal_entry_id', $journalIds)->orderBy('id')->get()->getResultArray();

        $this->seedDevelopment();

        self::assertSame($beforeStock, $this->stockValues($itemIds));
        self::assertSame($beforeMovements, $this->database->table('stock_movements')->whereIn('inventory_item_id', $itemIds)->orderBy('id')->get()->getResultArray());
        self::assertSame($beforeJournals, $this->openingJournals($itemIds));
        self::assertSame($beforeLines, $this->database->table('journal_lines')->whereIn('journal_entry_id', $journalIds)->orderBy('id')->get()->getResultArray());
    }

    public function testExistingLegacyStockWithoutOpeningIsNotAutomaticallyCorrected(): void
    {
        self::assertTrue($this->database->table('inventory_items')->insert([
            'name' => 'Tepung legacy', 'sku' => 'DEMO-TEPUNG', 'base_unit' => 'gram',
            'quantity_on_hand' => '1250.000', 'average_cost' => '18.0000', 'inventory_value' => '22500.00',
        ]));
        $itemId = (int) $this->database->insertID();
        $beforeStock = $this->stockValues([$itemId]);

        $this->seedDevelopment();

        self::assertSame($beforeStock, $this->stockValues([$itemId]));
        self::assertSame(0, $this->database->table('stock_movements')->where('inventory_item_id', $itemId)->countAllResults());
        self::assertSame([], $this->openingJournals([$itemId]));
    }

    private function seedDevelopment(): void
    {
        (new DevelopmentSeeder(config('Database'), $this->database))->setSilent(true)->run();
        self::assertTrue($this->database->transStatus());
        self::assertSame(1, $this->database->transDepth);
    }

    private function stockValues(array $itemIds): array
    {
        return $this->database->table('inventory_items')->select('id, quantity_on_hand, average_cost, inventory_value')
            ->whereIn('id', $itemIds)->orderBy('id')->get()->getResultArray();
    }

    private function openingJournals(array $itemIds): array
    {
        return $this->database->table('journal_entries')->where('source_type', 'development_opening')
            ->whereIn('source_id', $itemIds)->orderBy('id')->get()->getResultArray();
    }
}
