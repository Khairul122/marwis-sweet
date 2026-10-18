<?php

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ForeignKeyDeleteRulesTest extends CIUnitTestCase
{
    private ?BaseConnection $database = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = db_connect('tests', false);
        self::assertSame('MySQLi', $this->database->DBDriver);
        self::assertSame('marwis_sweet_e2e', $this->database->query('SELECT DATABASE() AS schema_name')->getRow('schema_name'));
        self::assertTrue($this->database->DBDebug);
        $this->database->transException(true);
        self::assertTrue($this->database->transBegin());
    }

    protected function tearDown(): void
    {
        if ($this->database !== null) {
            $this->database->transRollback();
            $this->database->close();
        }
        parent::tearDown();
    }

    public function testForeignKeysDoNotReverseRestrictOrSetNullDeleteRules(): void
    {
        $constraints = $this->database->query(
            'SELECT TABLE_NAME, CONSTRAINT_NAME, DELETE_RULE, UPDATE_RULE
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()',
        )->getResultArray();
        self::assertNotEmpty($constraints);

        $reversed = array_values(array_filter($constraints, static fn (array $constraint): bool =>
            $constraint['DELETE_RULE'] === 'CASCADE'
            && in_array($constraint['UPDATE_RULE'], ['RESTRICT', 'SET NULL'], true)
        ));
        self::assertSame([], $reversed, 'Aturan FK terbalik masih dapat menghapus riwayat transaksi.');
    }

    public function testDeletingProductWithSaleItemsIsRejectedByDatabase(): void
    {
        [$productId, $variantId] = $this->createProduct();
        $suffix = bin2hex(random_bytes(8));
        $userId = $this->insert('users', [
            'name' => 'Kasir uji FK', 'username' => 'fk-' . $suffix,
            'password_hash' => password_hash($suffix, PASSWORD_DEFAULT),
            'role' => 'cashier', 'status' => 'active',
        ]);
        $shiftId = $this->insert('shifts', [
            'shift_number' => 'FK-' . $suffix, 'cashier_id' => $userId,
            'opened_at' => date('Y-m-d H:i:s'),
        ]);
        $saleId = $this->insert('sales', [
            'sale_number' => 'FK-' . $suffix, 'idempotency_key' => 'fk-' . $suffix,
            'shift_id' => $shiftId, 'cashier_id' => $userId,
            'subtotal' => '10000.00', 'total' => '10000.00', 'paid_at' => date('Y-m-d H:i:s'),
        ]);
        $this->insert('sale_items', [
            'sale_id' => $saleId, 'product_id' => $productId, 'product_variant_id' => $variantId,
            'product_name' => 'Produk uji FK', 'variant_name' => 'Reguler', 'sku' => 'FK-' . $suffix,
            'quantity' => '1.000', 'unit_price' => '10000.00', 'line_total' => '10000.00',
        ]);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(1451);
        $this->database->table('products')->where('id', $productId)->delete();
    }

    public function testDeletingUnusedProductStillDeletesOwnedVariantsAndImages(): void
    {
        [$productId, $variantId] = $this->createProduct();
        $imageId = $this->insert('product_images', [
            'product_id' => $productId, 'image_path' => 'uploads/e2e/fk-test.jpg', 'alt_text' => 'Produk uji FK',
        ]);

        self::assertTrue($this->database->table('products')->where('id', $productId)->delete());
        self::assertSame(0, $this->database->table('products')->where('id', $productId)->countAllResults());
        self::assertSame(0, $this->database->table('product_variants')->where('id', $variantId)->countAllResults());
        self::assertSame(0, $this->database->table('product_images')->where('id', $imageId)->countAllResults());
    }

    private function createProduct(): array
    {
        $suffix = bin2hex(random_bytes(8));
        $categoryId = $this->insert('categories', ['name' => 'Kategori uji FK', 'slug' => 'fk-' . $suffix]);
        $productId = $this->insert('products', [
            'category_id' => $categoryId, 'name' => 'Produk uji FK', 'slug' => 'fk-' . $suffix,
        ]);
        $variantId = $this->insert('product_variants', [
            'product_id' => $productId, 'name' => 'Reguler', 'sku' => 'FK-' . $suffix,
            'price' => '10000.00', 'stock_mode' => 'none',
        ]);

        return [$productId, $variantId];
    }

    private function insert(string $table, array $values): int
    {
        self::assertTrue($this->database->table($table)->insert($values));

        return (int) $this->database->insertID();
    }
}
