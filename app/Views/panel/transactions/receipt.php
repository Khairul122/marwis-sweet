<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$statusLabels = ['paid' => 'Lunas', 'partially_refunded' => 'Refund sebagian', 'refunded' => 'Refund penuh', 'cancelled' => 'Dibatalkan'];
$refundStatusLabels = ['submitted' => 'Menunggu owner', 'posted' => 'Dibukukan', 'rejected' => 'Ditolak'];
$canRequestRefund = false;
foreach ($items as $item) {
    if ((float) $item['quantity'] - (float) $item['reserved_refund_quantity'] > 0.0001) {
        $canRequestRefund = true;
        break;
    }
}
?>
<div class="mx-auto max-w-5xl">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a class="button-secondary" href="<?= site_url('panel/transactions') ?>">Kembali ke transaksi</a>
        <button class="button-primary" type="button" onclick="window.print()">Cetak struk</button>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <article class="border-2 border-brand-cocoa bg-white p-5 sm:p-8" id="receipt">
            <header class="text-center">
                <p class="font-display text-2xl"><?= esc(env('BUSINESS_NAME', 'Marwis Sweet')) ?></p>
                <p class="mt-2 text-sm">Struk penjualan</p>
                <p class="mt-4 font-bold"><?= esc($sale['sale_number']) ?></p>
                <span class="status-label mt-3 <?= $sale['status'] === 'paid' ? 'bg-[#F0FDF4] text-status-success' : 'bg-surface-soft-yellow text-brand-chili' ?>"><?= esc($statusLabels[$sale['status']] ?? $sale['status']) ?></span>
            </header>
            <div class="receipt-rule my-6"></div>
            <dl class="grid grid-cols-[auto_1fr] gap-x-5 gap-y-2 text-sm">
                <dt>Waktu</dt><dd class="text-right"><?= esc(date('d/m/Y H:i', strtotime($sale['paid_at']))) ?></dd>
                <dt>Kasir</dt><dd class="text-right"><?= esc($sale['cashier_name']) ?></dd>
                <dt>Shift</dt><dd class="text-right"><?= esc($sale['shift_number']) ?></dd>
            </dl>
            <div class="receipt-rule my-6"></div>
            <ul class="space-y-4">
                <?php foreach ($items as $item): ?>
                    <li>
                        <div class="flex justify-between gap-4"><span class="font-bold"><?= esc($item['product_name']) ?> · <?= esc($item['variant_name']) ?></span><strong class="tabular-nums">Rp<?= number_format((float) $item['line_total'], 0, ',', '.') ?></strong></div>
                        <p class="mt-1 text-sm text-ink-muted"><?= number_format((float) $item['quantity'], 0, ',', '.') ?> × Rp<?= number_format((float) $item['unit_price'], 0, ',', '.') ?></p>
                        <?php $tops = json_decode((string) $item['topping_snapshot'], true) ?: []; ?>
                        <?php if ($tops !== []): ?><p class="mt-1 text-xs text-ink-muted">Topping: <?= esc(implode(', ', array_column($tops, 'name'))) ?></p><?php endif ?>
                        <?php if ((float) $item['posted_refund_quantity'] > 0): ?><p class="mt-1 text-xs font-bold text-brand-chili">Direfund: <?= number_format((float) $item['posted_refund_quantity'], 0, ',', '.') ?> unit</p><?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
            <div class="receipt-rule my-6"></div>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt>Subtotal</dt><dd class="tabular-nums">Rp<?= number_format((float) $sale['subtotal'], 0, ',', '.') ?></dd></div>
                <div class="flex justify-between"><dt>Diskon</dt><dd class="tabular-nums">-Rp<?= number_format((float) $sale['discount'], 0, ',', '.') ?></dd></div>
                <div class="flex justify-between text-lg font-black"><dt>Total awal</dt><dd class="tabular-nums">Rp<?= number_format((float) $sale['total'], 0, ',', '.') ?></dd></div>
                <?php if ($postedRefundTotal > 0): ?><div class="flex justify-between font-bold text-brand-chili"><dt>Refund dibukukan</dt><dd class="tabular-nums">-Rp<?= number_format($postedRefundTotal, 0, ',', '.') ?></dd></div><div class="flex justify-between border-t border-border-warm pt-2 font-black"><dt>Penjualan bersih</dt><dd class="tabular-nums">Rp<?= number_format(max(0, (float) $sale['total'] - $postedRefundTotal), 0, ',', '.') ?></dd></div><?php endif ?>
            </dl>
            <div class="receipt-rule my-6"></div>
            <h2 class="text-sm font-bold">Pembayaran</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <?php foreach ($payments as $payment): ?>
                    <li class="flex justify-between gap-4"><span><?= esc(ucwords(str_replace('_', ' ', $payment['method']))) ?></span><span class="tabular-nums">Rp<?= number_format((float) $payment['amount'], 0, ',', '.') ?></span></li>
                    <?php if ((float) $payment['change_amount'] > 0): ?><li class="flex justify-between gap-4"><span>Kembalian</span><span class="tabular-nums">Rp<?= number_format((float) $payment['change_amount'], 0, ',', '.') ?></span></li><?php endif ?>
                <?php endforeach ?>
            </ul>
            <p class="mt-8 text-center text-sm font-semibold">Terima kasih.</p>
        </article>

        <aside class="space-y-5 print:hidden lg:sticky lg:top-24">
            <?php if (($user['role'] ?? '') === 'cashier' && $canRequestRefund && in_array($sale['status'], ['paid', 'partially_refunded'], true)): ?>
                <section class="panel-card p-5">
                    <p class="section-kicker">Refund</p>
                    <h2 class="mt-2 font-display text-2xl">Ajukan pengembalian</h2>
                    <p class="mt-2 text-sm leading-6 text-ink-muted">Owner harus menyetujui sebelum dana dan status transaksi berubah.</p>
                    <form class="mt-5 space-y-4" action="<?= site_url('panel/transactions/' . $sale['id'] . '/refunds') ?>" method="post">
                        <?= csrf_field() ?>
                        <fieldset><legend class="form-label">Jumlah per item</legend><div class="space-y-3">
                            <?php foreach ($items as $item): ?><?php $available = max(0, (float) $item['quantity'] - (float) $item['reserved_refund_quantity']); ?>
                                <div class="border border-border-warm bg-surface-soft-yellow p-3 text-sm"><label class="grid grid-cols-[1fr_5rem] items-center gap-3"><span><strong class="block"><?= esc($item['product_name']) ?></strong><span class="text-xs text-ink-muted">Sisa <?= number_format($available, 0, ',', '.') ?> unit</span></span><input class="form-input" aria-label="Jumlah refund <?= esc($item['product_name']) ?>" name="quantities[<?= (int) $item['id'] ?>]" type="number" min="0" max="<?= esc((string) $available) ?>" step="1" value="0" <?= $available <= 0 ? 'disabled' : '' ?>></label><?php if ($item['direct_inventory_item_id']): ?><label class="mt-3 flex min-h-11 items-center gap-2 border-t border-border-warm pt-3 text-xs font-semibold"><input class="h-5 w-5 accent-brand-cocoa" type="checkbox" name="restock[<?= (int) $item['id'] ?>]" value="1" <?= $available <= 0 ? 'disabled' : '' ?>> Barang kembali dan layak dijual</label><?php endif ?></div>
                            <?php endforeach ?>
                        </div></fieldset>
                        <div><label class="form-label" for="refund-method">Metode pengembalian</label><select class="form-input" id="refund-method" name="method" required><option value="cash">Tunai</option><option value="qris">QRIS</option><option value="bank_transfer">Transfer bank</option><option value="debit">Kartu debit</option><option value="credit">Kartu kredit</option></select></div>
                        <div><label class="form-label" for="refund-reference">Nomor referensi <span class="font-normal text-ink-muted">(wajib non-tunai)</span></label><input class="form-input" id="refund-reference" name="reference_number" maxlength="120"></div>
                        <div><label class="form-label" for="refund-reason">Alasan refund</label><textarea class="form-input min-h-24" id="refund-reason" name="reason" minlength="5" maxlength="500" required></textarea></div>
                        <div class="border border-brand-cocoa bg-brand-sun p-3 text-xs font-semibold">Bahan produk resep tidak otomatis kembali. Restock hanya tersedia pada barang siap jual yang kembali dalam kondisi layak jual.</div>
                        <button class="button-primary w-full" type="submit">Kirim pengajuan</button>
                    </form>
                </section>
            <?php endif ?>

            <section class="panel-card p-5">
                <div class="flex items-center justify-between gap-3"><h2 class="font-bold">Riwayat refund</h2><span class="status-label bg-surface-soft-yellow"><?= count($refunds) ?></span></div>
                <?php if ($refunds === []): ?>
                    <p class="mt-4 text-sm text-ink-muted">Belum ada refund untuk transaksi ini.</p>
                <?php else: ?>
                    <div class="mt-4 space-y-4">
                        <?php foreach ($refunds as $refund): ?>
                            <article class="border border-border-warm p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2"><div><p class="text-xs font-bold text-ink-muted"><?= esc($refund['refund_number']) ?></p><p class="mt-1 font-black tabular-nums">Rp<?= number_format((float) $refund['amount'], 0, ',', '.') ?></p></div><span class="status-label <?= $refund['status'] === 'posted' ? 'bg-[#F0FDF4] text-status-success' : ($refund['status'] === 'rejected' ? 'bg-[#FEF2F2] text-status-danger' : 'bg-brand-sun') ?>"><?= esc($refundStatusLabels[$refund['status']] ?? $refund['status']) ?></span></div>
                                <ul class="mt-3 space-y-1 text-xs text-ink-muted"><?php foreach ($refund['items'] as $refundItem): ?><li><?= esc($refundItem['product_name']) ?> · <?= esc($refundItem['variant_name']) ?>, <?= number_format((float) $refundItem['quantity'], 0, ',', '.') ?> unit</li><?php endforeach ?></ul>
                                <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs"><dt>Metode</dt><dd class="text-right font-semibold"><?= esc(ucwords(str_replace('_', ' ', $refund['method']))) ?></dd><dt>Pemohon</dt><dd class="text-right font-semibold"><?= esc($refund['requester_name']) ?></dd><dt>Alasan</dt><dd class="text-right"><?= esc($refund['reason']) ?></dd></dl>
                                <?php if ($refund['status'] === 'rejected'): ?><p class="mt-3 border-l-4 border-status-danger pl-3 text-xs text-status-danger">Ditolak: <?= esc($refund['rejection_reason']) ?></p><?php endif ?>
                                <?php if (($user['role'] ?? '') === 'owner' && $refund['status'] === 'submitted'): ?>
                                    <div class="mt-4 space-y-3 border-t border-border-warm pt-4">
                                        <form action="<?= site_url('panel/refunds/' . $refund['id'] . '/approve') ?>" method="post" data-confirm-form="Setujui dan bukukan refund Rp<?= number_format((float) $refund['amount'], 0, ',', '.') ?>? Tindakan ini tidak dapat dibatalkan."><?= csrf_field() ?><button class="button-primary w-full" type="submit">Setujui dan bukukan</button></form>
                                        <form action="<?= site_url('panel/refunds/' . $refund['id'] . '/reject') ?>" method="post" class="space-y-2"><?= csrf_field() ?><label class="form-label" for="reject-<?= (int) $refund['id'] ?>">Alasan penolakan</label><textarea class="form-input min-h-20" id="reject-<?= (int) $refund['id'] ?>" name="rejection_reason" minlength="5" maxlength="500" required></textarea><button class="button-secondary w-full" type="submit">Tolak pengajuan</button></form>
                                    </div>
                                <?php endif ?>
                            </article>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </section>
        </aside>
    </div>
</div>
<?= $this->endSection() ?>
