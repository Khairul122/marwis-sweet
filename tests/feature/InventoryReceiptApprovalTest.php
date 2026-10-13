<?php

use App\Services\Money;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/** @internal */
final class InventoryReceiptApprovalTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private $database;
    private int $ownerId;
    private int $cashierId;
    private int $inventoryItemId;

    protected function setUp(): void
    {
        parent::setUp();
        $filters = config('Filters');
        $filters->globals['before'] = array_values(array_filter(
            $filters->globals['before'],
            static fn (string $filter): bool => $filter !== 'csrf',
        ));
        $this->database = db_connect();
        $this->ownerId = (int) $this->database->table('users')->where('username', 'task16owner')->get()->getRow('id');
        $this->cashierId = (int) $this->database->table('users')->where('username', 'task16cashier')->get()->getRow('id');
        $this->inventoryItemId = (int) $this->database->table('inventory_items')->where('sku', 'DEMO-TEPUNG')->get()->getRow('id');
        $this->assertGreaterThan(0, $this->ownerId);
        $this->assertGreaterThan(0, $this->cashierId);
        $this->assertGreaterThan(0, $this->inventoryItemId);
    }

    public function testCashierInventoryViewHidesBusinessValuationAndOwnerCanReviewIt(): void
    {
        $cashierPage = $this->withSession(['user_id' => $this->cashierId])->get('panel/inventory');
        $cashierPage->assertOK();
        $this->assertStringContainsString('Penerimaan dan kartu stok', $cashierPage->getBody());
        $this->assertStringNotContainsString('Biaya rata-rata (Rp)', $cashierPage->getBody());
        $this->assertStringNotContainsString('>Nilai</th>', $cashierPage->getBody());

        $ownerPage = $this->withSession(['user_id' => $this->ownerId])->get('panel/inventory');
        $ownerPage->assertOK();
        $this->assertStringContainsString('Biaya rata-rata (Rp)', $ownerPage->getBody());
        $this->assertStringContainsString('>Nilai</th>', $ownerPage->getBody());
    }

    public function testOnlyApprovedReceiptCanChangeStockOrJournal(): void
    {
        $beforeItem = $this->database->table('inventory_items')->where('id', $this->inventoryItemId)->get()->getRowArray();
        $reference = 'T16-' . strtoupper(bin2hex(random_bytes(4)));
        $created = $this->postAs($this->cashierId, 'panel/inventory/receipts', [
            'reference_number' => $reference,
            'document_date' => date('Y-m-d'),
            'payment_source' => 'bank',
            'notes' => 'Verifikasi penerimaan Task 16.',
        ]);
        $created->assertRedirect();
        $receiptUrl = $created->getRedirectUrl();
        self::assertIsString($receiptUrl);
        self::assertSame(1, preg_match('~/panel/inventory/receipts/([0-9]+)$~', $receiptUrl, $matches));
        $receiptId = (int) $matches[1];
        $itemInput = [
            'receipt_id' => $receiptId,
            'item_id' => $this->inventoryItemId,
            'purchase_quantity' => '2.500',
            'purchase_unit' => 'kg',
            'purchase_unit_cost' => '14500.00',
        ];
        $this->postAs($this->cashierId, 'panel/inventory/receipts/items', $itemInput)->assertRedirect();

        $this->postAs($this->cashierId, 'panel/inventory/receipts/post', ['receipt_id' => $receiptId])->assertRedirect();
        $this->postAs($this->cashierId, 'panel/inventory/receipts/' . $receiptId . '/approve')->assertRedirect();
        self::assertSame('draft', $this->receipt($receiptId)['status']);
        $this->assertNoPosting($receiptId, $beforeItem);

        $this->postAs($this->cashierId, 'panel/inventory/receipts/' . $receiptId . '/submit')->assertRedirect();
        self::assertSame('submitted', $this->receipt($receiptId)['status']);
        $this->postAs($this->ownerId, 'panel/inventory/receipts/' . $receiptId . '/reject', ['review_notes' => 'Biaya perlu dicocokkan dengan nota supplier.'])->assertRedirect();
        self::assertSame('rejected', $this->receipt($receiptId)['status']);
        $this->assertNoPosting($receiptId, $beforeItem);

        $itemInput['purchase_quantity'] = '3.000';
        $this->postAs($this->cashierId, 'panel/inventory/receipts/items', $itemInput)->assertRedirect();
        self::assertSame('rejected', $this->receipt($receiptId)['status']);
        $this->postAs($this->cashierId, 'panel/inventory/receipts/' . $receiptId . '/submit')->assertRedirect();
        $this->postAs($this->ownerId, 'panel/inventory/receipts/' . $receiptId . '/approve')->assertRedirect();
        self::assertSame('approved', $this->receipt($receiptId)['status']);
        $this->assertNoPosting($receiptId, $beforeItem);

        $this->postAs($this->cashierId, 'panel/inventory/receipts/items', $itemInput)->assertRedirect();
        self::assertSame('3.000', $this->database->table('goods_receipt_items')->where('goods_receipt_id', $receiptId)->get()->getRow('purchase_quantity'));
        $this->assertNoPosting($receiptId, $beforeItem);

        $this->postAs($this->cashierId, 'panel/inventory/receipts/post', ['receipt_id' => $receiptId])->assertRedirect();
        self::assertSame('posted', $this->receipt($receiptId)['status']);
        $afterItem = $this->database->table('inventory_items')->where('id', $this->inventoryItemId)->get()->getRowArray();
        self::assertSame(Money::quantityMilli($beforeItem['quantity_on_hand']) + 3_000_000, Money::quantityMilli($afterItem['quantity_on_hand']));
        self::assertSame(Money::cents($beforeItem['inventory_value']) + 4_350_000, Money::cents($afterItem['inventory_value']));
        self::assertSame(1, $this->database->table('stock_movements')->where(['source_type' => 'goods_receipt', 'source_id' => $receiptId])->countAllResults());
        $journal = $this->database->table('journal_entries')->where('source_key', 'goods_receipt:' . $receiptId)->get()->getRowArray();
        self::assertNotNull($journal);
        $debit = Money::cents($this->database->table('journal_lines')->selectSum('debit')->where('journal_entry_id', $journal['id'])->get()->getRow('debit'));
        $credit = Money::cents($this->database->table('journal_lines')->selectSum('credit')->where('journal_entry_id', $journal['id'])->get()->getRow('credit'));
        self::assertSame(4_350_000, $debit);
        self::assertSame($debit, $credit);
    }

    public function testCashierCannotOpenOwnerOnlyLegacyReceiptForm(): void
    {
        $response = $this->withSession(['user_id' => $this->cashierId])->get('panel/data/receipt/create');
        $response->assertRedirect();
    }

    private function postAs(int $userId, string $path, array $parameters = []): TestResponse
    {
        return $this->withSession(['user_id' => $userId])->post($path, $parameters);
    }

    private function receipt(int $receiptId): array
    {
        return $this->database->table('goods_receipts')->where('id', $receiptId)->get()->getRowArray();
    }

    private function assertNoPosting(int $receiptId, array $beforeItem): void
    {
        $afterItem = $this->database->table('inventory_items')->where('id', $this->inventoryItemId)->get()->getRowArray();
        self::assertSame(Money::quantityMilli($beforeItem['quantity_on_hand']), Money::quantityMilli($afterItem['quantity_on_hand']));
        self::assertSame(Money::cents($beforeItem['inventory_value']), Money::cents($afterItem['inventory_value']));
        self::assertSame(0, $this->database->table('stock_movements')->where(['source_type' => 'goods_receipt', 'source_id' => $receiptId])->countAllResults());
        self::assertSame(0, $this->database->table('journal_entries')->where('source_key', 'goods_receipt:' . $receiptId)->countAllResults());
    }
}
