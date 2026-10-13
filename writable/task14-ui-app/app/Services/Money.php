<?php

namespace App\Services;

use InvalidArgumentException;

final class Money
{
    public static function cents(mixed $value): int
    {
        return self::scaledInteger($value, 2);
    }

    public static function quantityMilli(mixed $value): int
    {
        return self::scaledInteger($value, 3);
    }

    public static function unitCostTenThousand(mixed $value): int
    {
        return self::scaledInteger($value, 4);
    }

    public static function quantityByFactor(int $quantityMilli, mixed $factor): int
    {
        $factorMillionths = self::factorMillionths($factor);
        if ($quantityMilli < 0 || $factorMillionths < 0) {
            throw new InvalidArgumentException('Konversi jumlah tidak valid.');
        }

        return self::multiplyDivideRounded($quantityMilli, $factorMillionths, 1000000);
    }

    public static function factorMillionths(mixed $value): int
    {
        return self::scaledInteger($value, 6);
    }

    public static function decimal(int $cents): string
    {
        if ($cents === PHP_INT_MIN) {
            throw new InvalidArgumentException('Nominal melewati batas aritmetika.');
        }
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);
        return $sign . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function quantity(int $milli): string
    {
        if ($milli === PHP_INT_MIN) {
            throw new InvalidArgumentException('Jumlah melewati batas aritmetika.');
        }
        $sign = $milli < 0 ? '-' : '';
        $absolute = abs($milli);
        return $sign . intdiv($absolute, 1000) . '.' . str_pad((string) ($absolute % 1000), 3, '0', STR_PAD_LEFT);
    }

    public static function unitCost(int $tenThousand): string
    {
        if ($tenThousand === PHP_INT_MIN) {
            throw new InvalidArgumentException('Biaya melewati batas aritmetika.');
        }
        $sign = $tenThousand < 0 ? '-' : '';
        $absolute = abs($tenThousand);
        return $sign . intdiv($absolute, 10000) . '.' . str_pad((string) ($absolute % 10000), 4, '0', STR_PAD_LEFT);
    }

    public static function centsForQuantity(int $quantityMilli, int $unitCostTenThousand): int
    {
        if ($quantityMilli < 0 || $unitCostTenThousand < 0) {
            throw new InvalidArgumentException('Biaya persediaan tidak boleh negatif.');
        }

        return self::multiplyDivideRounded($quantityMilli, $unitCostTenThousand, 100000);
    }

    public static function averageUnitCost(int $valueCents, int $quantityMilli): string
    {
        if ($valueCents < 0 || $quantityMilli <= 0) {
            throw new InvalidArgumentException('Nilai atau jumlah persediaan tidak valid.');
        }

        $scaledCost = self::multiplyDivideRounded($valueCents, 100000, $quantityMilli);
        return self::unitCost($scaledCost);
    }

    public static function proportionalCents(int $totalCents, int $part, int $whole): int
    {
        if ($totalCents < 0 || $part < 0 || $whole <= 0 || $part > $whole) {
            throw new InvalidArgumentException('Proporsi uang tidak valid.');
        }

        [$quotient, $remainder] = self::multiplyDivide($totalCents, $part, $whole);
        if ($remainder >= intdiv($whole, 2) + ($whole % 2)) {
            if ($quotient === PHP_INT_MAX) {
                throw new InvalidArgumentException('Hasil perhitungan melewati batas aritmetika.');
            }
            return $quotient + 1;
        }
        return $quotient;
    }

    public static function rupiahCents(int $amountCents): int
    {
        if ($amountCents < 0) {
            throw new InvalidArgumentException('Nominal Rupiah tidak boleh negatif.');
        }

        $rupiah = intdiv($amountCents, 100);
        if (($amountCents % 100) >= 50) {
            $rupiah++;
        }
        if ($rupiah > intdiv(PHP_INT_MAX, 100)) {
            throw new InvalidArgumentException('Nominal melewati batas aritmetika.');
        }

        return $rupiah * 100;
    }

    public static function percentageRupiahCents(int $baseCents, int $rateHundredths): int
    {
        if ($baseCents < 0 || $rateHundredths < 0 || $rateHundredths > 10000) {
            throw new InvalidArgumentException('Dasar atau tarif persentase tidak valid.');
        }

        return self::proportionalCents($baseCents, $rateHundredths, 1000000) * 100;
    }

    public static function proportionalRupiahCents(int $totalCents, int $part, int $whole): int
    {
        if ($totalCents % 100 !== 0) {
            return self::proportionalCents($totalCents, $part, $whole);
        }

        return self::proportionalCents(intdiv($totalCents, 100), $part, $whole) * 100;
    }

    /** @param list<int> $weights @return list<int> */
    public static function allocateRupiahCents(int $totalCents, array $weights): array
    {
        if ($totalCents % 100 !== 0) {
            return self::allocateCents($totalCents, $weights);
        }

        return array_map(
            static fn (int $amount): int => $amount * 100,
            self::allocateCents(intdiv($totalCents, 100), $weights),
        );
    }

    /** @param list<int> $weights @return list<int> */
    public static function allocateCents(int $totalCents, array $weights): array
    {
        if ($totalCents < 0 || array_filter($weights, static fn (int $weight): bool => $weight < 0) !== []) {
            throw new InvalidArgumentException('Alokasi uang tidak valid.');
        }
        if ($weights === []) {
            if ($totalCents === 0) {
                return [];
            }
            throw new InvalidArgumentException('Alokasi membutuhkan sedikitnya satu baris.');
        }

        $weightTotal = array_sum($weights);
        if ($weightTotal === 0) {
            $allocations = array_fill(0, count($weights), 0);
            $allocations[array_key_last($allocations)] = $totalCents;
            return $allocations;
        }

        $allocations = array_fill(0, count($weights), 0);
        $allocated = 0;
        $lastIndex = array_key_last($weights);
        foreach ($weights as $index => $weight) {
            if ($index === $lastIndex) {
                continue;
            }
            [$share] = self::multiplyDivide($totalCents, $weight, $weightTotal);
            $allocations[$index] = $share;
            $allocated += $share;
        }
        $allocations[$lastIndex] = $totalCents - $allocated;
        return $allocations;
    }

    private static function scaledInteger(mixed $value, int $scale): int
    {
        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new InvalidArgumentException('Nominal harus berupa angka terbatas.');
            }
            $text = sprintf('%.10F', $value);
        } elseif (is_int($value) || is_string($value)) {
            $text = trim((string) $value);
        } else {
            throw new InvalidArgumentException('Nominal tidak valid.');
        }

        if (!preg_match('/^([+-]?)(\d+)(?:\.(\d*))?$/', $text, $matches)) {
            throw new InvalidArgumentException('Nominal tidak valid.');
        }

        $negative = $matches[1] === '-';
        $wholeText = $matches[2];
        $factor = 10 ** $scale;
        $maximumWhole = intdiv(PHP_INT_MAX, $factor);
        if (strlen($wholeText) > strlen((string) $maximumWhole)
            || (strlen($wholeText) === strlen((string) $maximumWhole) && strcmp($wholeText, (string) $maximumWhole) > 0)) {
            throw new InvalidArgumentException('Nominal melewati batas aritmetika.');
        }
        $whole = (int) $wholeText;
        $fraction = $matches[3] ?? '';
        $kept = str_pad(substr($fraction, 0, $scale), $scale, '0');
        $scaledWhole = $whole * $factor;
        $fractionValue = (int) $kept;
        if ($fractionValue > PHP_INT_MAX - $scaledWhole) {
            throw new InvalidArgumentException('Nominal melewati batas aritmetika.');
        }
        $scaled = $scaledWhole + $fractionValue;
        if (strlen($fraction) > $scale && $fraction[$scale] >= '5') {
            if ($scaled === PHP_INT_MAX) {
                throw new InvalidArgumentException('Nominal melewati batas aritmetika.');
            }
            $scaled++;
        }

        return $negative ? -$scaled : $scaled;
    }

    private static function multiplyDivideRounded(int $multiplicand, int $multiplier, int $divisor): int
    {
        [$quotient, $remainder] = self::multiplyDivide($multiplicand, $multiplier, $divisor);
        if ($remainder >= intdiv($divisor, 2) + ($divisor % 2)) {
            if ($quotient === PHP_INT_MAX) {
                throw new InvalidArgumentException('Hasil perhitungan melewati batas aritmetika.');
            }
            return $quotient + 1;
        }
        return $quotient;
    }

    /** @return array{int, int} */
    private static function multiplyDivide(int $multiplicand, int $multiplier, int $divisor): array
    {
        if ($multiplicand < 0 || $multiplier < 0 || $divisor <= 0) {
            throw new InvalidArgumentException('Perhitungan proporsi tidak valid.');
        }

        $whole = intdiv($multiplicand, $divisor);
        $part = $multiplicand % $divisor;
        if ($whole > 0 && $multiplier > intdiv(PHP_INT_MAX, $whole)) {
            throw new InvalidArgumentException('Hasil perhitungan melewati batas aritmetika.');
        }
        $quotient = $whole * $multiplier;
        $remainderQuotient = 0;
        $remainder = 0;
        $bits = decbin($multiplier);

        for ($index = 0, $length = strlen($bits); $index < $length; $index++) {
            $doubleCarry = 0;
            if ($remainder >= $divisor - $remainder) {
                $remainder -= $divisor - $remainder;
                $doubleCarry = 1;
            } else {
                $remainder += $remainder;
            }

            $addCarry = 0;
            if ($bits[$index] === '1' && $part > 0) {
                if ($remainder >= $divisor - $part) {
                    $remainder -= $divisor - $part;
                    $addCarry = 1;
                } else {
                    $remainder += $part;
                }
            }

            $carry = $doubleCarry + $addCarry;
            if ($remainderQuotient > intdiv(PHP_INT_MAX - $carry, 2)) {
                throw new InvalidArgumentException('Hasil perhitungan melewati batas aritmetika.');
            }
            $remainderQuotient = ($remainderQuotient * 2) + $carry;
        }

        if ($quotient > PHP_INT_MAX - $remainderQuotient) {
            throw new InvalidArgumentException('Hasil perhitungan melewati batas aritmetika.');
        }

        return [$quotient + $remainderQuotient, $remainder];
    }
}
