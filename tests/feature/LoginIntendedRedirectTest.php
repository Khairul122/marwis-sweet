<?php

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

/** @internal */
final class LoginIntendedRedirectTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private ?BaseConnection $database = null;
    private array $accounts = [];
    private array $originalBeforeFilters = [];
    private string $password = 'RegressionLogin!2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = db_connect();
        self::assertSame('MySQLi', $this->database->DBDriver);
        self::assertSame('marwis_sweet_e2e', $this->database->query('SELECT DATABASE() AS schema_name')->getRow('schema_name'));
        self::assertSame('', $this->database->getPrefix());
        self::assertTrue($this->database->transBegin());

        $filters = config('Filters');
        $this->originalBeforeFilters = $filters->globals['before'];
        if (!in_array('csrf', $filters->globals['before'], true)) {
            $filters->globals['before'][] = 'csrf';
        }
        $suffix = bin2hex(random_bytes(8));
        foreach (['owner', 'cashier'] as $role) {
            $username = 'intended-' . $role . '-' . $suffix;
            self::assertTrue($this->database->table('users')->insert([
                'name' => 'Uji tujuan login ' . $role, 'username' => $username,
                'password_hash' => password_hash($this->password, PASSWORD_DEFAULT),
                'role' => $role, 'status' => 'active',
            ]));
            $this->accounts[$role] = ['id' => (int) $this->database->insertID(), 'username' => $username];
        }
    }

    protected function tearDown(): void
    {
        if ($this->database !== null) {
            $this->database->transRollback();
        }
        config('Filters')->globals['before'] = $this->originalBeforeFilters;
        parent::tearDown();
    }

    public function testLoginReturnsToLocalIntendedPathAndQueryAndConsumesIntent(): void
    {
        $intended = 'panel/reports?period_type=yearly&year=2026';
        $response = $this->login('owner', ['login_intended' => $intended]);

        $response->assertRedirectTo(site_url($intended));
        $response->assertSessionHas('user_id', (string) $this->accounts['owner']['id']);
        $response->assertSessionMissing('login_intended');
        self::assertNotEmpty($this->database->table('users')->where('id', $this->accounts['owner']['id'])->get()->getRow('last_login_at'));
    }

    #[DataProvider('unsafeDestinations')]
    public function testExternalIntentCannotRedirectOutsidePanel(string $intended): void
    {
        $response = $this->login('owner', ['login_intended' => $intended]);

        $response->assertRedirectTo(site_url('panel'));
        $response->assertSessionHas('user_id', (string) $this->accounts['owner']['id']);
        $response->assertSessionMissing('login_intended');
    }

    public static function unsafeDestinations(): iterable
    {
        yield 'external URL' => ['http://evil.invalid'];
        yield 'protocol-relative URL' => ['//evil.invalid'];
    }

    public function testDirectLoginWithoutIntentOpensPanel(): void
    {
        $response = $this->login('owner');

        $response->assertRedirectTo(site_url('panel'));
        $response->assertSessionHas('user_id', (string) $this->accounts['owner']['id']);
        $response->assertSessionMissing('login_intended');
    }

    public function testCashierIntendedOwnerPageStillPassesThroughRoleFilter(): void
    {
        $response = $this->login('cashier', ['login_intended' => 'panel/accounting']);
        $response->assertRedirectTo(site_url('panel/accounting'));
        $response->assertSessionMissing('login_intended');

        $denied = $this->withSession($_SESSION)->withHeaders([])->get('panel/accounting');
        $denied->assertRedirectTo(site_url('panel'));
        $denied->assertSessionHas('error', 'Halaman ini hanya dapat diakses owner.');
        self::assertStringNotContainsString('Jurnal umum', $denied->getBody());
    }

    public function testGuestGetStoresRelativePathAndQuery(): void
    {
        $intended = 'panel/reports?period_type=yearly&year=2026';
        $response = $this->withSession([])->withHeaders([])->get($intended);

        $response->assertRedirectTo(site_url('login'));
        $response->assertSessionHas('login_intended', $intended);
    }

    public function testGuestPostDoesNotOverwriteExistingIntent(): void
    {
        $intended = 'panel/reports?period_type=yearly&year=2026';
        $response = $this->withSession(['login_intended' => $intended])->withHeaders([])->post('panel/pos/finalize', [
            csrf_token() => csrf_hash(),
        ]);

        $response->assertRedirectTo(site_url('login'));
        $response->assertSessionHas('login_intended', $intended);
    }

    public function testGuestPdfFetchDoesNotOverwriteExistingIntent(): void
    {
        $intended = 'panel/reports?period_type=yearly&year=2026';
        $response = $this->withSession(['login_intended' => $intended])->withHeaders([
            'Accept' => 'application/pdf',
            'X-Requested-With' => 'XMLHttpRequest',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Dest' => 'empty',
        ])->get('panel/reports/sales.pdf?period_type=yearly&year=2026');

        $response->assertRedirectTo(site_url('login'));
        $response->assertSessionHas('login_intended', $intended);
    }

    private function login(string $role, array $session = []): TestResponse
    {
        return $this->withSession($session)->withHeaders([])->post('login', [
            'identity' => $this->accounts[$role]['username'], 'password' => $this->password,
            csrf_token() => csrf_hash(),
        ]);
    }
}
