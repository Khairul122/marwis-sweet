<?php

use App\Services\AuditLogService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/** @internal */
final class AuditLogWorkflowTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private $database;
    private int $ownerId;
    private int $cashierId;

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
        foreach ([['owner', 'owner'], ['cashier', 'cashier']] as [$name, $role]) {
            $this->database->table('users')->insert([
                'name' => 'Audit ' . ucfirst($name), 'username' => 't18' . $name . $suffix,
                'email' => null, 'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'role' => $role, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($role === 'owner') { $this->ownerId = (int) $this->database->insertID(); }
            else { $this->cashierId = (int) $this->database->insertID(); }
        }
    }

    public function testAuditIsSanitizedImmutableAndOwnerReadable(): void
    {
        $opaqueToken = str_repeat('a', 64);
        $eventId = (new AuditLogService($this->database))->record(
            $this->ownerId,
            'verification.audit.sanitized',
            'verification_record',
            't18-' . bin2hex(random_bytes(5)),
            'Pengujian jejak audit.',
            ['price' => '1000.00', 'password' => 'do-not-store', 'comment' => 'Token ' . $opaqueToken],
            ['price' => '1200.00', 'nested' => ['review_token' => $opaqueToken, 'safe' => 'ok']],
        );

        $row = $this->database->table('audit_events')->where('id', $eventId)->get()->getRowArray();
        self::assertStringContainsString('1000.00', $row['before_values']);
        self::assertStringNotContainsString('do-not-store', $row['before_values']);
        self::assertStringNotContainsString($opaqueToken, $row['before_values']);
        self::assertStringNotContainsString($opaqueToken, $row['after_values']);
        self::assertStringContainsString('[token disamarkan]', $row['before_values']);

        $updateBlocked = false;
        try {
            $result = $this->database->query('UPDATE audit_events SET reason = ? WHERE id = ?', ['diubah', $eventId]);
            $updateBlocked = $result === false;
        } catch (Throwable) {
            $updateBlocked = true;
        }
        self::assertTrue($updateBlocked, 'Trigger MySQL harus menolak perubahan audit.');

        $deleteBlocked = false;
        try {
            $result = $this->database->query('DELETE FROM audit_events WHERE id = ?', [$eventId]);
            $deleteBlocked = $result === false;
        } catch (Throwable) {
            $deleteBlocked = true;
        }
        self::assertTrue($deleteBlocked, 'Trigger MySQL harus menolak penghapusan audit.');
        self::assertSame(1, $this->database->table('audit_events')->where('id', $eventId)->countAllResults());

        $ownerPage = $this->withSession(['user_id' => $this->ownerId])->get('panel/audit');
        $ownerPage->assertOK();
        $ownerPage->assertSee('Riwayat perubahan');
        $ownerPage->assertSee('verification · audit · sanitized');
        $this->withSession(['user_id' => $this->cashierId])->get('panel/audit')->assertStatus(404);
        $this->withSession(['user_id' => $this->ownerId])->post('panel/audit')->assertStatus(404);
    }
}
