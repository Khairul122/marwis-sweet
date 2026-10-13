<?php

use App\Services\Money;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/** @internal */
final class StockAdjustmentWorkflowTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private $database;
    private int $ownerId;
    private int $cashierId;
    private int $itemId;
    private string $ownerUsername;
    private string $cashierUsername;

    protected function setUp(): void
    {
        parent::setUp();
        $filters = config('Filters');
        $filters->globals['before'] = array_values(array_filter(
            $filters->globals['before'],
            static fn (string $filter): bool => $filter !== 'csrf',
        ));
        $this->database = db_connect();
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $this->ownerUsername = 't17owner' . strtolower($suffix);
        $this->cashierUsername = 't17cashier' . strtolower($suffix);
        $now = date('Y-m-d H:i:s');
        foreach ([[$this->ownerUsername, 'owner'], [$this->cashierUsername, 'cashier']] as [$username, $role]) {
            $this->database->table('users')->insert([
                'name' => 'Task 17 ' . ucfirst($role),
                'username' => $username,
                'email' => null,
                'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'role' => $role,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($role === 'owner') {
                $this->ownerId = (int) $this->database->insertID();
            } else {
                $this->cashierId = (int) $this->database->insertID();
            }
        }
        $this->database->table('inventory_items')->insert([
            'name' => 'Bahan verifikasi Task 17',
            'sku' => 'T17-' . $suffix,
            'base_unit' => 'gram',
            'quantity_on_hand' => '0.000',
            'average_cost' => '0.0000',
            'inventory_value' => '0.00',
            'minimum_stock' => '0.000',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->itemId = (int) $this->database->insertID();
    }

    protected function tearDown(): void
    {
        if (isset($this->database, $this->ownerId, $this->cashierId)) {
            $adjustmentIds = array_column($this->database->table('stock_adjustments')
                ->select('id')->whereIn('created_by', [$this->ownerId, $this->cashierId])->get()->getResultArray(), 'id');
            if ($adjustmentIds !== []) {
                $entries = $this->database->table('journal_entries')->select('id')
                    ->groupStart()->whereIn('source_type', ['stock_adjustment', 'stock_adjustment_reversal'])->whereIn('source_id', $adjustmentIds)->groupEnd()
                    ->get()->getResultArray();
                $entryIds = array_column($entries, 'id');
                if ($entryIds !== []) {
                    $this->database->table('journal_lines')->whereIn('journal_entry_id', $entryIds)->delete();
                    $this->database->table('journal_entries')->whereIn('id', $entryIds)->delete();
                }
                $this->database->table('stock_movements')->groupStart()
                    ->whereIn('source_type', ['stock_adjustment', 'stock_adjustment_reversal'])->whereIn('source_id', $adjustmentIds)->groupEnd()->delete();
                $this->database->table('stock_adjustment_items')->whereIn('stock_adjustment_id', $adjustmentIds)->delete();
                $this->database->table('stock_adjustments')->whereIn('id', $adjustmentIds)->delete();
            }
            $this->database->table('inventory_items')->where('id', $this->itemId)->delete();
            $this->database->table('users')->whereIn('id', [$this->ownerId, $this->cashierId])->delete();
        }
        parent::tearDown();
    }

    public function testOpeningAndDamageAreApprovedBeforePostingAndCanBeReversed(): void
    {
        $before = $this->inventoryItem();
        $accountId = (int) $this->database->table('accounts')->where('system_key', 'owner_capital')->get()->getRow('id');
        $openingId = $this->createDraft('opening', 'Saldo bahan saat mulai operasional', $accountId);
        $this->addLine($openingId, '10.000', '2500.0000');
        $newPage = $this->withSession(['user_id' => $this->cashierId])->get('panel/inventory/adjustments/new');
        $newPage->assertOK();
        $newPage->assertSee('Buat penyesuaian stok');
        $detailPage = $this->withSession(['user_id' => $this->ownerId])->get('panel/inventory/adjustments/' . $openingId);
        $detailPage->assertOK();
        $detailPage->assertSee('Rincian perubahan stok');
        $detailPage->assertSee('Ajukan ke owner');
        $this->postAs($this->cashierId, 'panel/inventory/adjustments/' . $openingId . '/submit')->assertRedirect();
        self::assertSame('submitted', $this->adjustment($openingId)['status']);
        $this->assertNoPosting($openingId, $before);

        $this->postAs($this->cashierId, 'panel/inventory/adjustments/post', ['adjustment_id' => $openingId])->assertRedirect();
        self::assertSame('submitted', $this->adjustment($openingId)['status']);
        $this->postAs($this->cashierId, 'panel/inventory/adjustments/' . $openingId . '/approve')->assertRedirect();
        self::assertSame('submitted', $this->adjustment($openingId)['status']);
        $this->postAs($this->ownerId, 'panel/inventory/adjustments/' . $openingId . '/approve')->assertRedirect();
        $this->postAs($this->cashierId, 'panel/inventory/adjustments/post', ['adjustment_id' => $openingId])->assertRedirect();
        self::assertSame('posted', $this->adjustment($openingId)['status']);
        self::assertSame(10_000, Money::quantityMilli($this->inventoryItem()['quantity_on_hand']));
        self::assertSame(2_500_000, Money::cents($this->inventoryItem()['inventory_value']));
        $openingJournal = $this->journal('stock_adjustment:' . $openingId);
        self::assertNotNull($openingJournal);
        self::assertSame($accountId, (int) $this->database->table('journal_lines')->where('journal_entry_id', $openingJournal['id'])->where('account_id', $accountId)->get()->getRow('account_id'));
        $this->assertJournalBalanced((int) $openingJournal['id']);

        $damageId = $this->createDraft('damage', 'Bahan rusak saat penyimpanan', null);
        $this->addLine($damageId, '2.500', '0');
        $this->postAs($this->cashierId, 'panel/inventory/adjustments/' . $damageId . '/submit')->assertRedirect();
        $this->postAs($this->ownerId, 'panel/inventory/adjustments/' . $damageId . '/approve')->assertRedirect();
        $this->postAs($this->cashierId, 'panel/inventory/adjustments/post', ['adjustment_id' => $damageId])->assertRedirect();
        self::assertSame(7_500, Money::quantityMilli($this->inventoryItem()['quantity_on_hand']));
        self::assertSame(1_875_000, Money::cents($this->inventoryItem()['inventory_value']));
        $damageJournal = $this->journal('stock_adjustment:' . $damageId);
        self::assertNotNull($damageJournal);
        $this->assertJournalBalanced((int) $damageJournal['id']);

        $reverse = $this->postAs($this->ownerId, 'panel/inventory/adjustments/' . $damageId . '/reverse', ['reason' => 'Catatan kerusakan dibatalkan']);
        $reverse->assertRedirect();
        $reversalId = $this->idFromRedirect($reverse);
        self::assertSame('posted', $this->adjustment($reversalId)['status']);
        self::assertSame($damageId, (int) $this->adjustment($reversalId)['reversal_of_id']);
        self::assertSame(10_000, Money::quantityMilli($this->inventoryItem()['quantity_on_hand']));
        self::assertSame(2_500_000, Money::cents($this->inventoryItem()['inventory_value']));
        $reversalJournal = $this->journal('stock_adjustment_reversal:' . $reversalId);
        self::assertNotNull($reversalJournal);
        self::assertSame((int) $damageJournal['id'], (int) $reversalJournal['reversal_of_id']);
        $this->assertJournalBalanced((int) $reversalJournal['id']);

        $this->postAs($this->ownerId, 'panel/inventory/adjustments/' . $damageId . '/reverse', ['reason' => 'Mencoba membalik dua kali'])->assertRedirect();
        self::assertSame(1, $this->database->table('stock_adjustments')->where('reversal_of_id', $damageId)->countAllResults());
        $this->postAs($this->cashierId, 'panel/inventory/adjustments/items', ['adjustment_id' => $openingId, 'item_id' => $this->itemId, 'quantity_delta' => '999.000', 'unit_cost' => '2500'])->assertRedirect();
        self::assertSame(10_000, Money::quantityMilli($this->inventoryItem()['quantity_on_hand']));
    }

    public function testCorrectionUsesBookValueAndInventoryOpeningCannotUseJournalOnlyRoute(): void
    {
        $this->database->table('inventory_items')->where('id', $this->itemId)->update([
            'quantity_on_hand' => '12.000', 'average_cost' => '2500.0000', 'inventory_value' => '30000.00',
        ]);
        $correctionId = $this->createDraft('correction', 'Koreksi hitung stok fisik', null);
        $this->addLine($correctionId, '-3.000', '0');
        $this->postAs($this->cashierId, 'panel/inventory/adjustments/' . $correctionId . '/submit')->assertRedirect();
        $line = $this->database->table('stock_adjustment_items')->where('stock_adjustment_id', $correctionId)->get()->getRowArray();
        self::assertSame('-7500.00', $line['value_delta']);
        $this->postAs($this->ownerId, 'panel/inventory/adjustments/' . $correctionId . '/reject', ['review_notes' => 'Cocokkan kembali dengan hitungan fisik']);
        self::assertSame('rejected', $this->adjustment($correctionId)['status']);
        self::assertSame(12_000, Money::quantityMilli($this->inventoryItem()['quantity_on_hand']));

        $this->addLine($correctionId, '-13.000', '0');
        $this->postAs($this->cashierId, 'panel/inventory/adjustments/' . $correctionId . '/submit')->assertRedirect();
        self::assertSame('rejected', $this->adjustment($correctionId)['status']);
        self::assertSame(12_000, Money::quantityMilli($this->inventoryItem()['quantity_on_hand']));

        $inventoryAccountId = (int) $this->database->table('accounts')->where('system_key', 'inventory')->get()->getRow('id');
        $capitalAccountId = (int) $this->database->table('accounts')->where('system_key', 'owner_capital')->get()->getRow('id');
        $this->postAs($this->ownerId, 'panel/accounting/opening-balances', [
            'entry_date' => date('Y-m-d'), 'debit_account_id' => $inventoryAccountId,
            'credit_account_id' => $capitalAccountId, 'amount' => '1.00', 'description' => 'Saldo persediaan langsung',
        ])->assertRedirect();
        self::assertSame(0, $this->database->table('journal_entries')->where('source_key', 'opening_balance:' . $inventoryAccountId)->countAllResults());

        $ownerPage = $this->withSession(['user_id' => $this->ownerId])->get('panel/inventory/adjustments');
        $ownerPage->assertOK();
        $ownerPage->assertSee('Buat penyesuaian');
        $cashierPage = $this->withSession(['user_id' => $this->cashierId])->get('panel/inventory/adjustments');
        $cashierPage->assertOK();
        $cashierPage->assertSee('Penyesuaian yang saya buat');
    }

    private function createDraft(string $type, string $reason, ?int $accountId): int
    {
        $response = $this->postAs($this->cashierId, 'panel/inventory/adjustments', [
            'adjustment_type' => $type,
            'document_date' => date('Y-m-d'),
            'reason' => $reason,
            'offset_account_id' => $accountId === null ? '' : (string) $accountId,
        ]);
        $response->assertRedirect();
        return $this->idFromRedirect($response);
    }

    private function addLine(int $adjustmentId, string $quantity, string $unitCost): void
    {
        $this->postAs($this->cashierId, 'panel/inventory/adjustments/items', [
            'adjustment_id' => $adjustmentId, 'item_id' => $this->itemId,
            'quantity_delta' => $quantity, 'unit_cost' => $unitCost,
        ])->assertRedirect();
    }

    private function postAs(int $userId, string $path, array $parameters = []): TestResponse
    {
        return $this->withSession(['user_id' => $userId])->post($path, $parameters);
    }

    private function idFromRedirect(TestResponse $response): int
    {
        self::assertSame(1, preg_match('~/panel/inventory/adjustments/([0-9]+)$~', (string) $response->getRedirectUrl(), $matches));
        return (int) $matches[1];
    }

    private function adjustment(int $adjustmentId): array
    {
        return $this->database->table('stock_adjustments')->where('id', $adjustmentId)->get()->getRowArray();
    }

    private function inventoryItem(): array
    {
        return $this->database->table('inventory_items')->where('id', $this->itemId)->get()->getRowArray();
    }

    private function journal(string $sourceKey): ?array
    {
        return $this->database->table('journal_entries')->where('source_key', $sourceKey)->get()->getRowArray();
    }

    private function assertNoPosting(int $adjustmentId, array $before): void
    {
        $after = $this->inventoryItem();
        self::assertSame(Money::quantityMilli($before['quantity_on_hand']), Money::quantityMilli($after['quantity_on_hand']));
        self::assertSame(Money::cents($before['inventory_value']), Money::cents($after['inventory_value']));
        self::assertSame(0, $this->database->table('stock_movements')->where(['source_type' => 'stock_adjustment', 'source_id' => $adjustmentId])->countAllResults());
        self::assertSame(0, $this->database->table('journal_entries')->where('source_key', 'stock_adjustment:' . $adjustmentId)->countAllResults());
    }

    private function assertJournalBalanced(int $journalId): void
    {
        $totals = $this->database->table('journal_lines')->selectSum('debit', 'debit')->selectSum('credit', 'credit')->where('journal_entry_id', $journalId)->get()->getRowArray();
        self::assertSame(Money::cents($totals['debit']), Money::cents($totals['credit']));
    }
}
