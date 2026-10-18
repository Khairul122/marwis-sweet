<?php

function payment_method_label(string $method): string
{
    return match ($method) {
        'cash' => 'Tunai',
        'qris' => 'QRIS',
        'bank_transfer' => 'Transfer bank',
        'debit' => 'Kartu debit',
        'credit' => 'Kartu kredit',
        default => ucwords(str_replace('_', ' ', $method)),
    };
}
