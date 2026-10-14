<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/** @internal */
final class PublicDemoContentWorkflowTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private $database;
    private ?array $previousDemoSetting = null;
    private array $bannerIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = db_connect();
        $this->previousDemoSetting = $this->database->table('settings')->where('setting_key', 'business_profile_is_demo')->get()->getRowArray();
    }

    protected function tearDown(): void
    {
        if ($this->bannerIds !== []) {
            $this->database->table('banners')->whereIn('id', $this->bannerIds)->delete();
        }
        $this->database->table('settings')->where('setting_key', 'business_profile_is_demo')->delete();
        if ($this->previousDemoSetting !== null) {
            $this->database->table('settings')->insert($this->previousDemoSetting);
        }
        parent::tearDown();
    }

    public function testDemoIdentityIsDisclosedAndDemoBannerIsNotPublished(): void
    {
        $this->database->table('settings')->replace([
            'setting_key' => 'business_profile_is_demo',
            'setting_value' => '1',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->database->table('banners')->insert([
            'title' => 'Banner internal demo untuk uji',
            'image_path' => 'uploads/demo/banners/example.jpg',
            'alt_text' => 'Materi contoh banner yang tidak boleh tampil publik',
            'link_url' => null,
            'sort_order' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->bannerIds[] = (int) $this->database->insertID();

        $page = $this->get('/');

        $page->assertOK();
        $page->assertSee('Tampilan data contoh. Identitas, menu, dan informasi usaha di halaman ini belum terverifikasi.');
        $page->assertDontSee('Banner internal demo untuk uji');
    }

    public function testLegacyProfileWithoutDemoMarkerDefaultsToDemo(): void
    {
        $this->database->table('settings')->where('setting_key', 'business_profile_is_demo')->delete();

        $page = $this->get('/');

        $page->assertOK();
        $page->assertSee('Tampilan data contoh. Identitas, menu, dan informasi usaha di halaman ini belum terverifikasi.');
    }
}
