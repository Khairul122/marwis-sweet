<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class PosOrderService
{
    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    public static function normalizeDetails(array $payload): array
    {
        $rawType = $payload['order_type'] ?? 'takeaway';
        if (!is_string($rawType)) {
            throw new RuntimeException('Jenis pesanan tidak valid.');
        }
        $type = $rawType;
        if (!in_array($type, ['dine_in', 'takeaway'], true)) {
            throw new RuntimeException('Jenis pesanan tidak valid.');
        }

        $rawTableNumber = $payload['table_number'] ?? '';
        $rawCustomerName = $payload['customer_name'] ?? '';
        if (!is_string($rawTableNumber) || !is_string($rawCustomerName)) {
            throw new RuntimeException('Nomor meja atau nama pelanggan tidak valid.');
        }
        $tableNumber = trim(strip_tags($rawTableNumber));
        $customerName = trim(strip_tags($rawCustomerName));
        if (mb_strlen($tableNumber) > 30 || mb_strlen($customerName) > 120) {
            throw new RuntimeException('Nomor meja atau nama pelanggan terlalu panjang.');
        }
        if ($type !== 'dine_in') {
            $tableNumber = '';
        }

        return [
            'order_type' => $type,
            'table_number' => $tableNumber !== '' ? $tableNumber : null,
            'customer_name' => $customerName !== '' ? $customerName : null,
        ];
    }

    public static function normalizeCart(array $cart): array
    {
        if ($cart === [] || count($cart) > 100) {
            throw new RuntimeException('Keranjang kosong atau berisi terlalu banyak baris.');
        }

        $items = [];
        foreach ($cart as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Isi keranjang tidak valid.');
            }

            $rawVariantId = $entry['variant_id'] ?? null;
            $rawQuantity = $entry['quantity'] ?? null;
            if ((!is_int($rawVariantId) && !is_string($rawVariantId)) || (!is_int($rawQuantity) && !is_string($rawQuantity))) {
                throw new RuntimeException('Isi keranjang tidak valid.');
            }
            $variantId = filter_var($rawVariantId, FILTER_VALIDATE_INT);
            $quantity = filter_var($rawQuantity, FILTER_VALIDATE_INT);
            if ($variantId === false || $variantId < 1 || $quantity === false || $quantity < 1 || $quantity > 99) {
                throw new RuntimeException('Isi keranjang tidak valid.');
            }

            $rawToppings = $entry['topping_ids'] ?? [];
            if (!is_array($rawToppings) || count($rawToppings) > 20) {
                throw new RuntimeException('Pilihan topping tidak valid.');
            }
            $toppingIds = [];
            foreach ($rawToppings as $rawTopping) {
                if (!is_int($rawTopping) && !is_string($rawTopping)) {
                    throw new RuntimeException('Pilihan topping tidak valid.');
                }
                $toppingId = filter_var($rawTopping, FILTER_VALIDATE_INT);
                if ($toppingId === false || $toppingId < 1) {
                    throw new RuntimeException('Pilihan topping tidak valid.');
                }
                $toppingIds[] = $toppingId;
            }

            $discountType = (string) ($entry['discount_type'] ?? 'amount');
            if (!in_array($discountType, ['amount', 'percent'], true)) {
                throw new RuntimeException('Jenis diskon item tidak valid.');
            }
            $discountValue = $entry['discount_value'] ?? 0;
            if (!is_int($discountValue) && !is_float($discountValue) && !is_string($discountValue)) {
                throw new RuntimeException('Nilai diskon item tidak valid.');
            }
            if (!is_numeric($discountValue) || !is_finite((float) $discountValue) || (float) $discountValue < 0 || (float) $discountValue > 9999999999999.99) {
                throw new RuntimeException('Nilai diskon item tidak valid.');
            }
            if ($discountType === 'percent' && (float) $discountValue > 100) {
                throw new RuntimeException('Diskon item tidak boleh melebihi 100%.');
            }

            $rawNote = $entry['note'] ?? '';
            if (!is_string($rawNote)) {
                throw new RuntimeException('Catatan item tidak valid.');
            }
            $note = trim(strip_tags($rawNote));
            if (mb_strlen($note) > 300) {
                throw new RuntimeException('Catatan item maksimal 300 karakter.');
            }

            $items[] = [
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'topping_ids' => array_values(array_unique($toppingIds)),
                'discount_type' => $discountType,
                'discount_value' => number_format((float) $discountValue, 2, '.', ''),
                'note' => $note,
            ];
        }

        return $items;
    }

    public function save(array $payload, int $cashierId, ?int $orderId = null): int
    {
        $cart = $payload['cart'] ?? [];
        if (!is_array($cart)) {
            throw new RuntimeException('Data keranjang tidak valid.');
        }
        $items = self::normalizeCart($cart);
        $details = self::normalizeDetails($payload);
        $items = $this->addDisplaySnapshots($items);

        try {
            $this->database->transException(true)->transStart();
            $shift = $this->database->table('shifts')->where('cashier_id', $cashierId)->where('status', 'open')->get()->getRowArray();
            if (!$shift) {
                throw new RuntimeException('Buka shift sebelum menyimpan pesanan.');
            }

            $now = date('Y-m-d H:i:s');
            $values = [
                ...$details,
                'items_json' => json_encode($items, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ];
            if ($orderId !== null) {
                $order = $this->database->query(
                    'SELECT id FROM pos_orders WHERE id = ? AND cashier_id = ? AND status = ? FOR UPDATE',
                    [$orderId, $cashierId, 'held'],
                )->getRowArray();
                if (!$order) {
                    throw new RuntimeException('Pesanan tersimpan sudah tidak tersedia untuk diubah.');
                }
                $this->database->table('pos_orders')->where('id', $orderId)->update($values);
                $savedId = $orderId;
            } else {
                $orderNumber = 'ORD-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
                $this->database->table('pos_orders')->insert([
                    'order_number' => $orderNumber,
                    'cashier_id' => $cashierId,
                    ...$values,
                    'status' => 'held',
                    'created_at' => $now,
                ]);
                $savedId = (int) $this->database->insertID();
            }

            $this->database->transComplete();
            if (!$this->database->transStatus()) {
                throw new RuntimeException('Pesanan belum berhasil disimpan.');
            }

            return $savedId;
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function heldForCashier(int $cashierId): array
    {
        $orders = $this->database->table('pos_orders')->where('cashier_id', $cashierId)->where('status', 'held')->orderBy('updated_at', 'DESC')->get()->getResultArray();
        foreach ($orders as &$order) {
            $order['items'] = $this->decodeItems($order['items_json']);
        }
        unset($order);

        return $orders;
    }

    public function findHeld(int $orderId, int $cashierId): ?array
    {
        if ($orderId < 1) {
            return null;
        }
        $order = $this->database->table('pos_orders')->where('id', $orderId)->where('cashier_id', $cashierId)->where('status', 'held')->get()->getRowArray();
        if (!$order) {
            return null;
        }
        $order['items'] = $this->decodeItems($order['items_json']);
        return $order;
    }

    public function cancel(int $orderId, int $cashierId): void
    {
        try {
            $this->database->transException(true)->transStart();
            $order = $this->database->query(
                'SELECT id FROM pos_orders WHERE id = ? AND cashier_id = ? AND status = ? FOR UPDATE',
                [$orderId, $cashierId, 'held'],
            )->getRowArray();
            if (!$order) {
                throw new RuntimeException('Pesanan sudah dibayar, dibatalkan, atau bukan milik akun ini.');
            }

            $now = date('Y-m-d H:i:s');
            $this->database->table('pos_orders')->where('id', $orderId)->update([
                'status' => 'cancelled',
                'cancelled_at' => $now,
                'updated_at' => $now,
            ]);
            $this->database->transComplete();
            if (!$this->database->transStatus()) {
                throw new RuntimeException('Pesanan belum berhasil dibatalkan.');
            }
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    private function addDisplaySnapshots(array $items): array
    {
        foreach ($items as &$item) {
            $variant = $this->database->table('product_variants')
                ->select('product_variants.name AS variant_name, product_variants.price, product_variants.stock_mode, products.name AS product_name')
                ->join('products', 'products.id = product_variants.product_id')
                ->where('product_variants.id', $item['variant_id'])->get()->getRowArray();
            if (!$variant) {
                throw new RuntimeException('Salah satu produk pada keranjang sudah tidak ditemukan.');
            }
            if ($item['topping_ids'] !== [] && $variant['stock_mode'] !== 'recipe') {
                throw new RuntimeException('Topping hanya dapat dipilih untuk produk dengan resep.');
            }

            $toppings = [];
            if ($item['topping_ids'] !== []) {
                $rows = $this->database->table('toppings')->whereIn('id', $item['topping_ids'])->get()->getResultArray();
                if (count($rows) !== count($item['topping_ids'])) {
                    throw new RuntimeException('Salah satu topping pada keranjang sudah tidak ditemukan.');
                }
                foreach ($rows as $topping) {
                    $toppings[] = ['id' => (int) $topping['id'], 'name' => $topping['name'], 'price' => $topping['additional_price']];
                }
            }

            $item['product_name'] = $variant['product_name'];
            $item['variant_name'] = $variant['variant_name'];
            $item['unit_price'] = $variant['price'];
            $item['toppings'] = $toppings;
        }
        unset($item);

        return $items;
    }

    private function decodeItems(string $json): array
    {
        $items = json_decode($json, true);
        return is_array($items) ? $items : [];
    }
}
