<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 14mm 17mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #211814; font-size: 8.5px; line-height: 1.45; }
        p { margin: 0; }
        .document-header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #211814; margin-bottom: 14px; }
        .document-header td { padding: 0 0 11px; vertical-align: top; }
        .brand-cell { width: 62%; }
        .meta-cell { width: 38%; text-align: right; }
        .brand-mark { display: inline-block; width: 34px; height: 34px; margin-right: 8px; padding-top: 9px; background: #facc15; border: 1px solid #211814; text-align: center; font-size: 12px; font-weight: bold; vertical-align: top; }
        .brand-copy { display: inline-block; vertical-align: top; }
        .business-name { margin-top: 1px; font-size: 14px; font-weight: bold; }
        .business-detail { margin-top: 3px; color: #6f625a; font-size: 7.5px; }
        .document-type { color: #a33b22; font-size: 7px; font-weight: bold; letter-spacing: 1.1px; text-transform: uppercase; }
        .document-title { margin-top: 3px; font-size: 19px; font-weight: bold; line-height: 1.2; }
        .document-meta { margin-top: 4px; color: #6f625a; font-size: 7.5px; }
        .filter-strip { margin-bottom: 13px; padding: 7px 9px; background: #fff8d8; border-left: 3px solid #facc15; color: #554942; }
        .summary { width: 100%; margin-bottom: 15px; border-collapse: separate; border-spacing: 5px 0; }
        .summary td { width: 25%; padding: 9px; border: 1px solid #d8cec4; background: #fff; vertical-align: top; }
        .summary td:first-child { border-color: #211814; background: #facc15; }
        .label { color: #6f625a; font-size: 6.5px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; }
        .summary td:first-child .label { color: #211814; }
        .value { margin-top: 5px; font-size: 13px; font-weight: bold; white-space: nowrap; }
        .section-title { margin: 15px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #211814; font-size: 10px; font-weight: bold; }
        .data { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        .data thead { display: table-header-group; }
        .data tr { page-break-inside: avoid; }
        .data th { padding: 5px 6px; border-bottom: 1px solid #211814; background: #f4efe9; color: #4d4039; font-size: 6.8px; letter-spacing: .35px; text-align: left; text-transform: uppercase; }
        .data td { padding: 5px 6px; border-bottom: 1px solid #e5ded7; vertical-align: top; }
        .data tbody tr:nth-child(even) td { background: #fbfaf8; }
        .right { text-align: right !important; white-space: nowrap; }
        .empty { padding: 12px !important; color: #786c65; text-align: center; }
        .totals-wrap { width: 100%; border-collapse: collapse; }
        .totals-wrap td { vertical-align: top; }
        .totals-note { width: 42%; padding: 9px; background: #f4efe9; color: #62564f; }
        .totals-cell { width: 58%; padding-left: 14px; }
        .totals { width: 100%; border-collapse: collapse; }
        .totals td { padding: 4px 0; border-bottom: 1px solid #e5ded7; }
        .totals .grand td { padding-top: 7px; border-top: 1.5px solid #211814; border-bottom: 0; font-size: 10px; font-weight: bold; }
        .footer { position: fixed; right: 0; bottom: -10mm; left: 0; padding-top: 4px; border-top: 1px solid #d8cec4; color: #786c65; font-size: 6.8px; }
        .footer-right { float: right; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
<?php
$money = static fn (float $value): string => 'Rp' . number_format($value, 0, ',', '.');
$businessDetails = array_filter([$settings['address'] ?? null, $settings['phone'] ?? null]);
?>
<table class="document-header">
    <tr>
        <td class="brand-cell">
            <span class="brand-mark">MS</span>
            <span class="brand-copy">
                <span class="business-name"><?= esc($businessName) ?></span><br>
                <span class="business-detail"><?= $businessDetails === [] ? 'Laporan operasional usaha' : esc(implode(' · ', $businessDetails)) ?></span>
            </span>
        </td>
        <td class="meta-cell">
            <div class="document-type">Dokumen laporan</div>
            <div class="document-title">Penjualan</div>
            <div class="document-meta">Periode <?= esc($report['period']['label']) ?> · <?= esc(ucfirst($report['period']['type'])) ?></div>
            <div class="document-meta">Dibuat <?= esc($printedAt) ?> · <?= esc($printedBy) ?></div>
        </td>
    </tr>
</table>

<div class="footer">
    <span><?= esc($businessName) ?> · Laporan Penjualan · <?= esc($report['period']['label']) ?></span>
    <span class="footer-right">Halaman <span class="page-number"></span></span>
</div>

<div class="filter-strip"><strong>Cakupan:</strong> <?= $filterLabels === [] ? 'Seluruh transaksi pada periode terpilih' : esc(implode(' · ', $filterLabels)) ?></div>

<table class="summary">
    <tr>
        <td><div class="label">Penjualan bersih</div><div class="value"><?= $money((float) $report['totals']['net_sales']) ?></div></td>
        <td><div class="label">Transaksi</div><div class="value"><?= number_format($report['totals']['transactions'], 0, ',', '.') ?></div></td>
        <td><div class="label">Unit terjual</div><div class="value"><?= number_format($report['totals']['units'], 3, ',', '.') ?></div></td>
        <td><div class="label">Refund</div><div class="value"><?= $money((float) $report['totals']['refunds']) ?></div></td>
    </tr>
</table>

<div class="section-title">Ringkasan Nilai</div>
<table class="totals-wrap">
    <tr>
        <td class="totals-note">Nilai dihitung dari transaksi yang masuk pada periode dan filter laporan. Refund mengikuti tanggal posting refund.</td>
        <td class="totals-cell">
            <table class="totals">
                <tr><td>Omzet sebelum diskon</td><td class="right"><?= $money((float) $report['totals']['gross']) ?></td></tr>
                <tr><td>Diskon</td><td class="right"><?= $money((float) $report['totals']['discount']) ?></td></tr>
                <tr><td>Pajak</td><td class="right"><?= $money((float) $report['totals']['tax']) ?></td></tr>
                <tr><td>Biaya layanan</td><td class="right"><?= $money((float) $report['totals']['service']) ?></td></tr>
                <tr><td>Refund</td><td class="right"><?= $money((float) $report['totals']['refunds']) ?></td></tr>
                <?php if ($report['includeFinancials']): ?><tr><td>HPP bersih</td><td class="right"><?= $money((float) $report['totals']['cogs']) ?></td></tr><tr class="grand"><td>Laba kotor</td><td class="right"><?= $money((float) $report['totals']['gross_profit']) ?></td></tr><?php endif ?>
            </table>
        </td>
    </tr>
</table>

<div class="section-title">Penerimaan dan Refund per Metode</div>
<table class="data"><thead><tr><th>Metode</th><th class="right">Penerimaan</th><th class="right">Refund</th><th class="right">Bersih</th></tr></thead><tbody>
<?php $paymentMethods = array_values(array_unique(array_merge(array_keys($report['paymentTotals']), array_keys($report['refundByMethod'])))); ?>
<?php if ($paymentMethods === []): ?><tr><td class="empty" colspan="4">Tidak ada data pada periode ini.</td></tr>
<?php else: ?><?php foreach ($paymentMethods as $method): ?><?php $amount = (float) ($report['paymentTotals'][$method] ?? 0); $returned = (float) ($report['refundByMethod'][$method] ?? 0); ?><tr><td><?= esc(payment_method_label($method)) ?></td><td class="right"><?= $money($amount) ?></td><td class="right"><?= $money($returned) ?></td><td class="right"><?= $money($amount - $returned) ?></td></tr><?php endforeach ?><?php endif ?>
</tbody></table>

<div class="section-title">Produk Terlaris</div>
<table class="data"><thead><tr><th>Produk</th><th>Varian</th><th class="right">Unit</th><th class="right">Nilai</th></tr></thead><tbody>
<?php if ($report['products'] === []): ?><tr><td class="empty" colspan="4">Tidak ada data pada periode ini.</td></tr>
<?php else: ?><?php foreach (array_slice($report['products'], 0, 15) as $product): ?><tr><td><?= esc($product['product_name']) ?></td><td><?= esc($product['variant_name']) ?></td><td class="right"><?= number_format($product['quantity'], 3, ',', '.') ?></td><td class="right"><?= $money((float) $product['sales']) ?></td></tr><?php endforeach ?><?php endif ?>
</tbody></table>

<div class="section-title">Detail Transaksi</div>
<?php if ($report['salesDetailsTruncated'] || $report['refundDetailsTruncated']): ?><div class="filter-strip">Total dan rekap mencakup seluruh data periode. Rincian PDF dibatasi <?= number_format($report['salesPageSize'], 0, ',', '.') ?> baris per tabel; gunakan filter periode lebih pendek untuk mengunduh seluruh rincian.</div><?php endif ?>
<table class="data"><thead><tr><th>Nomor</th><th>Waktu</th><th>Kasir</th><th>Shift</th><th class="right">Subtotal laporan</th><th class="right">Total laporan</th></tr></thead><tbody>
<?php if ($report['sales'] === []): ?><tr><td class="empty" colspan="6">Tidak ada data pada periode ini.</td></tr>
<?php else: ?><?php foreach ($report['sales'] as $sale): ?><tr><td><?= esc($sale['sale_number']) ?></td><td><?= esc(date('d/m/Y H:i', strtotime($sale['paid_at']))) ?></td><td><?= esc($sale['cashier_name']) ?></td><td><?= esc($sale['shift_number']) ?></td><td class="right"><?= $money((float) $sale['report_subtotal']) ?></td><td class="right"><strong><?= $money((float) $sale['report_total']) ?></strong></td></tr><?php endforeach ?><?php endif ?>
</tbody></table>

<div class="section-title">Refund Diposting</div>
<table class="data"><thead><tr><th>Refund</th><th>Transaksi asal</th><th>Waktu</th><th>Metode</th><th class="right">Nominal</th></tr></thead><tbody>
<?php if ($report['refunds'] === []): ?><tr><td class="empty" colspan="5">Tidak ada refund yang diposting pada periode ini.</td></tr>
<?php else: ?><?php foreach ($report['refunds'] as $refund): ?><tr><td><?= esc($refund['refund_number']) ?></td><td><?= esc($refund['sale_number']) ?></td><td><?= esc(date('d/m/Y H:i', strtotime($refund['posted_at']))) ?></td><td><?= esc(payment_method_label($refund['method'])) ?></td><td class="right"><?= $money((float) $refund['report_amount']) ?></td></tr><?php endforeach ?><?php endif ?>
</tbody></table>
</body>
</html>
