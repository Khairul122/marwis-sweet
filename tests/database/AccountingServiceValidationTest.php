<?php

use App\Services\AccountingService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class AccountingServiceValidationTest extends CIUnitTestCase
{
    private ?BaseConnection $database = null;
    private int $userId;
    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = db_connect('tests', false);
        self::assertSame('MySQLi', $this->database->DBDriver);
        self::assertSame('marwis_sweet_e2e', $this->database->query('SELECT DATABASE() AS schema_name')->getRow('schema_name'));
        $this->database->transException(true);
        self::assertTrue($this->database->transBegin());
        $suffix = bin2hex(random_bytes(8));
        $this->database->table('users')->insert([
            'name' => 'Owner uji jurnal', 'username' => 'journal-' . $suffix,
            'password_hash' => password_hash($suffix, PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active',
        ]);
        $this->userId = (int) $this->database->insertID();
        $this->database->table('accounts')->insert([
            'code' => 'T-' . $suffix, 'name' => 'Akun uji jurnal', 'account_type' => 'asset',
            'normal_balance' => 'debit', 'is_active' => true,
        ]);
        $this->accountId = (int) $this->database->insertID();
    }

    protected function tearDown(): void
    {
        if ($this->database !== null) {
            $this->database->transRollback();
            $this->database->close();
        }
        parent::tearDown();
    }

    public function testUnequalDebitsAndCreditsAreRejectedWithoutPosting(): void
    {
        $this->assertPostingRejected([
            ['account_id' => $this->accountId, 'debit' => '10000.00', 'credit' => '0.00'],
            ['account_id' => $this->accountId, 'debit' => '0.00', 'credit' => '9000.00'],
        ], 'Total debit dan kredit jurnal harus seimbang.');
    }

    public function testLineWithBothDebitAndCreditIsRejectedWithoutPosting(): void
    {
        $this->assertPostingRejected([
            ['account_id' => $this->accountId, 'debit' => '10000.00', 'credit' => '10000.00'],
        ], 'Setiap baris jurnal harus memiliki debit atau kredit.');
    }

    public function testZeroValueLineIsRejectedWithoutPosting(): void
    {
        $this->assertPostingRejected([
            ['account_id' => $this->accountId, 'debit' => '10000.00', 'credit' => '0.00'],
            ['account_id' => $this->accountId, 'debit' => '0.00', 'credit' => '10000.00'],
            ['account_id' => $this->accountId, 'debit' => '0.00', 'credit' => '0.00'],
        ], 'Setiap baris jurnal harus memiliki debit atau kredit.');
    }

    private function assertPostingRejected(array $lines, string $message): void
    {
        $beforeEntries = $this->database->table('journal_entries')->countAllResults();
        $beforeLines = $this->database->table('journal_lines')->countAllResults();
        $exception = null;
        try {
            (new AccountingService($this->database))->post(
                'validation_test', $this->userId, '2099-12-31', 'Verifikasi penolakan jurnal', $lines, $this->userId,
            );
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        self::assertSame($beforeEntries, $this->database->table('journal_entries')->countAllResults());
        self::assertSame($beforeLines, $this->database->table('journal_lines')->countAllResults());
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame($message, $exception->getMessage());
    }
}
