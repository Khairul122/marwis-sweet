<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Cookie;

/** @internal */
final class DeploymentConfigurationTest extends CIUnitTestCase
{
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['app.baseURL', 'app.forceGlobalSecureRequests', 'app.proxyIPs', 'cookie.secure'] as $key) {
            $this->originalEnvironment[$key] = $_ENV[$key] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        parent::tearDown();
    }

    public function testHttpLanConfigurationDoesNotForceHttpsOrSecureCookies(): void
    {
        $_ENV['app.baseURL'] = 'http://192.168.1.20:8080';
        $_ENV['app.forceGlobalSecureRequests'] = 'true';
        $_ENV['app.proxyIPs'] = '';

        $app = new App();
        $cookie = new Cookie();

        self::assertSame('http://192.168.1.20:8080/', $app->baseURL);
        self::assertFalse($app->forceGlobalSecureRequests);
        self::assertSame([], $app->proxyIPs);
        self::assertFalse($cookie->secure);
    }

    public function testHttpsConfigurationEnablesSecureCookiesAndOnlyValidTrustedProxies(): void
    {
        $_ENV['app.baseURL'] = 'https://shop.example.test/marwis';
        $_ENV['app.forceGlobalSecureRequests'] = 'true';
        $_ENV['app.proxyIPs'] = '127.0.0.1,10.0.0.0/24,300.1.1.1,10.0.0.0/99';

        $app = new App();
        $cookie = new Cookie();

        self::assertSame('https://shop.example.test/marwis/', $app->baseURL);
        self::assertTrue($app->forceGlobalSecureRequests);
        self::assertSame(['127.0.0.1' => 'X-Forwarded-For', '10.0.0.0/24' => 'X-Forwarded-For'], $app->proxyIPs);
        self::assertTrue($cookie->secure);
    }
}
