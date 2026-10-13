<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class SalesPolicyService
{
    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    /** @return array<string, mixed> */
    public function current(): array
    {
        $settings = [];
        foreach ($this->database->table('settings')->whereIn('setting_key', array_keys(self::defaults()))->get()->getResultArray() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $values = array_replace(self::defaults(), $settings);
        foreach (['item_discount_max_amount', 'transaction_discount_max_amount'] as $key) {
            $values[$key . '_cents'] = $this->nonNegativeMoney($values[$key]);
        }
        foreach (['item_discount_max_percent', 'transaction_discount_max_percent', 'service_charge_rate', 'tax_rate'] as $key) {
            $values[$key . '_hundredths'] = $this->percentage($values[$key]);
        }
        $values['service_charge_enabled'] = $values['service_charge_enabled'] === '1';
        $values['tax_enabled'] = $values['tax_enabled'] === '1';
        return $values;
    }

    /** @return array<string, string> */
    public static function defaults(): array
    {
        return [
            'item_discount_max_amount' => '0.00',
            'item_discount_max_percent' => '0.00',
            'transaction_discount_max_amount' => '0.00',
            'transaction_discount_max_percent' => '0.00',
            'service_charge_enabled' => '0',
            'service_charge_rate' => '0.00',
            'tax_enabled' => '0',
            'tax_rate' => '0.00',
        ];
    }

    /** @return array{amount_cents: int, value_cents: int, type: string, over_limit: bool} */
    public static function calculateDiscount(int $baseCents, string $type, mixed $value, int $maxAmountCents, int $maxPercentHundredths): array
    {
        if ($baseCents < 0 || !in_array($type, ['amount', 'percent'], true)) {
            throw new RuntimeException('Jenis diskon atau nilai dasar tidak valid.');
        }

        $valueCents = Money::cents($value);
        if ($valueCents < 0) {
            throw new RuntimeException('Diskon tidak boleh bernilai negatif.');
        }

        if ($type === 'percent') {
            if ($valueCents > 10000) {
                throw new RuntimeException('Persentase diskon tidak boleh melebihi 100%.');
            }
            $amountCents = Money::percentageRupiahCents($baseCents, $valueCents);
        } else {
            if ($valueCents % 100 !== 0) {
                throw new RuntimeException('Nominal diskon harus berupa Rupiah bulat.');
            }
            $amountCents = $valueCents;
        }

        if ($amountCents > $baseCents) {
            throw new RuntimeException('Diskon tidak boleh melebihi nilai barang.');
        }

        $overLimit = $amountCents > 0 && $maxAmountCents === 0 && $maxPercentHundredths === 0;
        if ($maxAmountCents > 0 && $amountCents > $maxAmountCents) {
            $overLimit = true;
        }
        if ($maxPercentHundredths > 0 && $amountCents > Money::percentageRupiahCents($baseCents, $maxPercentHundredths)) {
            $overLimit = true;
        }

        return ['amount_cents' => $amountCents, 'value_cents' => $valueCents, 'type' => $type, 'over_limit' => $overLimit];
    }

    public static function chargeCents(int $baseCents, int $rateHundredths): int
    {
        if ($baseCents < 0 || $rateHundredths < 0 || $rateHundredths > 10000) {
            throw new RuntimeException('Tarif pajak atau biaya layanan tidak valid.');
        }

        return Money::percentageRupiahCents($baseCents, $rateHundredths);
    }

    private function nonNegativeMoney(mixed $value): int
    {
        try {
            return max(0, Money::cents($value));
        } catch (\Throwable) {
            return 0;
        }
    }

    private function percentage(mixed $value): int
    {
        try {
            $percentage = Money::cents($value);
            return min(10000, max(0, $percentage));
        } catch (\Throwable) {
            return 0;
        }
    }
}
