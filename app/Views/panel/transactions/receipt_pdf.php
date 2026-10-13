<?php
$orderTypeLabels = ['dine_in' => 'Makan di tempat', 'takeaway' => 'Bawa pulang'];
$money = static fn (mixed $amount): string => 'Rp' . number_format((float) $amount, 0, ',', '.');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk <?= esc($sale['sale_number']) ?></title>
    <style>
        @page { margin: 30mm 24mm; }
        * { box-sizing: border-box; }
        body { color: #211711; font: 10pt Arial, sans-serif; line-height: 1.45; }
        .brand { border-bottom: 3px solid #f7bf18; padding: 0 0 14px; margin-bottom: 18px; }
        .business { font-size: 19pt; font-weight: 700; margin: 0; }
        .muted { color: #665c55; }
        .small { font-size: 8.5pt; }
        .title { display: flex; justify-content: space-between; border-bottom: 1px solid #d9c9ad; padding-bottom: 10px; margin-bottom: 14px; }
        .title h1 { font-size: 14pt; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 5px 0; vertical-align: top; }
        .meta td:first-child { color: #665c55; width: 34%; }
        .items { margin-top: 18px; border-top: 1px solid #d9c9ad; border-bottom: 1px solid #d9c9ad; }
        .items th { color: #665c55; font-size: 8pt; text-align: left; text-transform: uppercase; }
        .items .right, .totals td:last-child { text-align: right; }
        .item-name { font-weight: 700; }
        .totals { margin-top: 12px; }
        .grand td { border-top: 2px solid #211711; font-size: 13pt; font-weight: 700; padding-top: 9px; }
        .payments { background: #fff9e8; border-left: 3px solid #f7bf18; margin-top: 18px; padding: 10px 12px; }
        .review { border: 1px solid #d9c9ad; margin-top: 20px; padding: 12px; }
        .review-url { overflow-wrap: anywhere; font-size: 8pt; }
        .thanks { border-top: 1px solid #d9c9ad; margin-top: 24px; padding-top: 12px; text-align: center; font-weight: 700; }
    </style>
</head>
<body>
    <header class="brand">
        <p class="business"><?= esc($businessSettings['business_name'] ?? env('BUSINESS_NAME', 'Marwis Sweet')) ?></p>
        <?php if (!empty($businessSettings['address'])): ?><p class="small muted"><?= nl2br(esc($businessSettings['address'])) ?></p><?php endif ?>
        <?php if (!empty($businessSettings['phone'])): ?><p class="small muted">Tel. <?= esc($businessSettings['phone']) ?></p><?php endif ?>
    </header>
    <div class="title"><h1>Struk penjualan</h1><strong><?= esc($sale['sale_number']) ?></strong></div>
    <table class="meta">
        <tr><td>Waktu</td><td><?= esc(date('d/m/Y H:i', strtotime($sale['paid_at']))) ?></td></tr>
        <tr><td>Kasir</td><td><?= esc($sale['cashier_name']) ?></td></tr>
        <tr><td>Shift</td><td><?= esc($sale['shift_number']) ?></td></tr>
        <tr><td>Jenis pesanan</td><td><?= esc($orderTypeLabels[$sale['order_type'] ?? 'takeaway'] ?? 'Bawa pulang') ?></td></tr>
        <?php if (($sale['table_number'] ?? '') !== ''): ?><tr><td>Meja</td><td><?= esc($sale['table_number']) ?></td></tr><?php endif ?>
        <?php if (($sale['customer_name'] ?? '') !== ''): ?><tr><td>Pelanggan</td><td><?= esc($sale['customer_name']) ?></td></tr><?php endif ?>
    </table>
    <table class="items">
        <thead><tr><th>Produk</th><th class="right">Jumlah</th><th class="right">Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr><td><span class="item-name"><?= esc($item['product_name']) ?> · <?= esc($item['variant_name']) ?></span><br><span class="small muted"><?= $money($item['unit_price']) ?> / unit</span><?php $tops = json_decode((string) $item['topping_snapshot'], true) ?: []; ?><?php if ($tops !== []): ?><br><span class="small muted">Topping: <?= esc(implode(', ', array_column($tops, 'name'))) ?></span><?php endif ?><?php if ((float) ($item['item_discount_amount'] ?? 0) > 0): ?><br><span class="small muted">Diskon item: -<?= $money($item['item_discount_amount']) ?></span><?php endif ?></td><td class="right"><?= number_format((float) $item['quantity'], 0, ',', '.') ?></td><td class="right"><?= $money($item['line_total']) ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <table class="totals">
        <tr><td>Subtotal</td><td><?= $money($sale['subtotal']) ?></td></tr>
        <?php if ((float) ($sale['item_discount'] ?? 0) > 0): ?><tr><td>Diskon item</td><td>-<?= $money($sale['item_discount']) ?></td></tr><?php endif ?>
        <?php if ((float) ($sale['transaction_discount'] ?? $sale['discount']) > 0): ?><tr><td>Diskon transaksi</td><td>-<?= $money($sale['transaction_discount'] ?? $sale['discount']) ?></td></tr><?php endif ?>
        <?php if ((float) $sale['service_charge'] > 0): ?><tr><td>Biaya layanan</td><td><?= $money($sale['service_charge']) ?></td></tr><?php endif ?>
        <?php if ((float) $sale['tax'] > 0): ?><tr><td>Pajak</td><td><?= $money($sale['tax']) ?></td></tr><?php endif ?>
        <tr class="grand"><td>Total awal</td><td><?= $money($sale['total']) ?></td></tr>
        <?php if ($postedRefundTotal > 0): ?><tr><td>Refund dibukukan</td><td>-<?= $money($postedRefundTotal) ?></td></tr><tr class="grand"><td>Penjualan bersih</td><td><?= $money(max(0, (float) $sale['total'] - $postedRefundTotal)) ?></td></tr><?php endif ?>
    </table>
    <section class="payments"><strong>Pembayaran</strong><table><?php foreach ($payments as $payment): ?><tr><td><?= esc(ucwords(str_replace('_', ' ', $payment['method']))) ?></td><td style="text-align:right"><?= $money($payment['amount']) ?></td></tr><?php if ((float) $payment['change_amount'] > 0): ?><tr><td>Kembalian</td><td style="text-align:right"><?= $money($payment['change_amount']) ?></td></tr><?php endif ?><?php endforeach ?></table></section>
    <?php if ($reviewToken): ?><section class="review"><strong>Bagikan pengalaman Anda</strong><p class="review-url"><?= esc(site_url('ulasan/' . $reviewToken)) ?></p><p class="small muted">Tautan berlaku 30 hari.</p></section><?php endif ?>
    <p class="thanks">Terima kasih telah berbelanja.</p>
</body>
</html>
