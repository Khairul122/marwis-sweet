<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$url = 'panel/inventory/receipts/' . (int) $receipt['id'];
$isOwner = ($user['role'] ?? '') === 'owner';
$totalCents = array_sum(array_map(static fn (array $line): int => \App\Services\Money::cents($line['line_total']), $lines));
$moneyWithCents = static fn (mixed $amount): string => 'Rp' . number_format((float) $amount, 2, ',', '.');
$unitCost = static fn (mixed $amount): string => 'Rp' . number_format((float) $amount, 4, ',', '.');
$statusLabels = [
    'draft' => 'Draft',
    'submitted' => 'Menunggu persetujuan owner',
    'approved' => 'Disetujui, siap diposting',
    'rejected' => 'Perlu perbaikan',
    'posted' => 'Sudah diposting',
    'cancelled' => 'Dibatalkan',
];
$lineRows = array_map(static fn (array $line): array => [
    '<strong>' . esc($line['item_name']) . '</strong>',
    panel_qty($line['purchase_quantity']) . ' ' . esc($line['purchase_unit']),
    panel_qty($line['base_quantity']) . ' ' . esc($line['base_unit']),
    $unitCost($line['purchase_unit_cost']),
    $moneyWithCents($line['line_total']),
], $lines);
?>
<nav class="mb-6 text-sm" aria-label="Breadcrumb"><a class="font-bold underline decoration-brand-sun decoration-2 underline-offset-4 focus-ring" href="<?= site_url('panel/inventory') ?>">Kembali ke daftar stok</a></nav>
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <p class="section-kicker">Penerimaan stok</p>
        <h1 class="mt-2 break-words font-display text-3xl leading-tight sm:text-4xl"><?= esc($receipt['reference_number']) ?></h1>
        <p class="mt-3 text-ink-muted"><?= esc(date('d/m/Y', strtotime($receipt['document_date']))) ?> · <?= esc($receipt['supplier_name'] ?: 'Tanpa supplier') ?> · dibuat oleh <?= esc($receipt['creator_name']) ?></p>
    </div>
    <p class="shrink-0 text-left sm:text-right"><span class="block text-sm text-ink-muted">Total biaya</span><span class="text-2xl font-black tabular-nums"><?= esc($moneyWithCents(\App\Services\Money::decimal($totalCents))) ?></span></p>
</div>

<section class="panel-card mb-6 p-5 sm:p-6" aria-label="Status penerimaan">
    <p class="text-sm font-bold text-ink-muted">Status dokumen</p>
    <h2 class="mt-1 text-lg font-extrabold"><?= esc($statusLabels[$receipt['status']] ?? 'Status tidak dikenal') ?></h2>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-ink-muted">
        <?php if ($receipt['status'] === 'draft'): ?>Lengkapi item dan biaya, lalu ajukan untuk diperiksa owner. Saldo stok dan jurnal belum berubah.
        <?php elseif ($receipt['status'] === 'submitted'): ?>Owner sedang memeriksa jumlah, biaya, supplier, dan sumber pembayaran. Penerimaan belum menambah stok atau jurnal.
        <?php elseif ($receipt['status'] === 'approved'): ?>Owner menyetujui biaya. Stok dan jurnal baru berubah setelah penerimaan diposting.
        <?php elseif ($receipt['status'] === 'rejected'): ?>Perbaiki item atau biaya sesuai catatan owner, lalu ajukan kembali. Saldo stok dan jurnal belum berubah.
        <?php elseif ($receipt['status'] === 'posted'): ?>Penerimaan sudah masuk kartu stok dan jurnal.
        <?php else: ?>Penerimaan ini dibatalkan dan tidak dapat diposting.<?php endif ?>
    </p>
    <dl class="mt-4 grid gap-x-6 gap-y-2 border-t border-border-warm pt-4 text-sm sm:grid-cols-2">
        <div><dt class="text-ink-muted">Diajukan oleh</dt><dd class="font-semibold"><?= esc($receipt['submitter_name'] ?: 'Belum diajukan') ?><?= $receipt['submitted_at'] ? ' · ' . esc(date('d/m/Y H:i', strtotime($receipt['submitted_at']))) : '' ?></dd></div>
        <?php if ($receipt['reviewed_by']): ?><div><dt class="text-ink-muted">Diperiksa oleh</dt><dd class="font-semibold"><?= esc($receipt['reviewer_name']) ?> · <?= esc(date('d/m/Y H:i', strtotime($receipt['reviewed_at']))) ?></dd></div><?php endif ?>
        <?php if ($receipt['posted_by']): ?><div><dt class="text-ink-muted">Diposting oleh</dt><dd class="font-semibold"><?= esc($receipt['poster_name']) ?> · <?= esc(date('d/m/Y H:i', strtotime($receipt['posted_at']))) ?></dd></div><?php endif ?>
        <?php if ($receipt['payment_source']): ?><div><dt class="text-ink-muted">Sumber pembayaran</dt><dd class="font-semibold"><?= esc(['cash' => 'Kas usaha', 'bank' => 'Bank', 'payable' => 'Utang supplier'][$receipt['payment_source']] ?? $receipt['payment_source']) ?></dd></div><?php endif ?>
    </dl>
    <?php if ($receipt['review_notes']): ?><div class="mt-4 border border-status-warning bg-[#FFFCF4] p-4 text-sm"><h3 class="font-bold">Catatan owner</h3><p class="mt-1 leading-6"><?= nl2br(esc($receipt['review_notes'])) ?></p></div><?php endif ?>
</section>

<?= panel_table('Item dan biaya yang diperiksa', ['Bahan', '>Jumlah beli', '>Masuk stok', '>Biaya per satuan', '>Total'], $lineRows, ['search' => false, 'width' => '44rem', 'empty' => 'Belum ada item. Tambahkan bahan yang diterima di bawah.']) ?>
<?php if ($receipt['notes']): ?><p class="mb-6 text-sm text-ink-muted">Catatan dokumen: <?= nl2br(esc($receipt['notes'])) ?></p><?php endif ?>

<?php if ($canEdit): ?>
<section class="panel-card mb-8 p-5 sm:p-6">
    <h2 class="font-bold">Tambah atau perbarui item</h2>
    <p class="mt-1 text-sm text-ink-muted">Simpan item yang sama untuk memperbarui jumlah dan biaya sebelum diajukan.</p>
    <form action="<?= site_url('panel/inventory/receipts/items') ?>" method="post" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
        <?= csrf_field() ?><input type="hidden" name="receipt_id" value="<?= (int) $receipt['id'] ?>">
        <div><label class="form-label" for="receipt-item">Bahan</label><select class="form-input" id="receipt-item" name="item_id" required><option value="">Pilih bahan</option><?php foreach ($items as $item): ?><option value="<?= (int) $item['id'] ?>"><?= esc($item['name']) ?> (<?= esc($item['base_unit']) ?>)</option><?php endforeach ?></select></div>
        <div><label class="form-label" for="purchase-quantity">Jumlah beli</label><input class="form-input" id="purchase-quantity" name="purchase_quantity" type="number" min="0.001" step="0.001" value="<?= esc(old('purchase_quantity')) ?>" required></div>
        <div><label class="form-label" for="purchase-unit">Satuan beli</label><input class="form-input" id="purchase-unit" name="purchase_unit" maxlength="30" placeholder="kg, liter, pcs" value="<?= esc(old('purchase_unit')) ?>" required></div>
        <div><label class="form-label" for="purchase-unit-cost">Biaya per satuan (Rp)</label><input class="form-input" id="purchase-unit-cost" name="purchase_unit_cost" type="number" min="0" step="0.01" value="<?= esc(old('purchase_unit_cost')) ?>" required></div>
        <button class="button-secondary justify-center" type="submit">Simpan item</button>
    </form>
    <div class="mt-5 flex flex-col gap-3 border-t border-border-warm pt-5 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-ink-muted"><?= count($lines) ?> item · total <?= esc($moneyWithCents(\App\Services\Money::decimal($totalCents))) ?></p>
        <?php if ($lines === []): ?><button class="button-primary cursor-not-allowed opacity-50" type="button" disabled aria-disabled="true">Ajukan ke owner</button>
        <?php else: ?><?= panel_post($url . '/submit', [], 'Ajukan ke owner', 'button-primary', 'Ajukan biaya penerimaan ini untuk diperiksa owner? Saldo stok dan jurnal belum berubah.') ?><?php endif ?>
    </div>
</section>
<?php endif ?>

<?php if ($canReview): ?>
<section class="panel-card mb-8 p-5 sm:p-6">
    <h2 class="font-bold">Keputusan owner</h2>
    <p class="mt-1 text-sm text-ink-muted">Periksa daftar bahan, konversi jumlah, biaya, dan sumber pembayaran sebelum memberi keputusan.</p>
    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <form action="<?= site_url($url . '/approve') ?>" method="post" data-confirm-form="Setujui biaya penerimaan ini? Stok dan jurnal tetap menunggu sampai penerimaan diposting.">
            <?= csrf_field() ?><h3 class="font-bold">Setujui biaya</h3><p class="mt-1 text-sm text-ink-muted">Setelah disetujui, kasir dapat mem-posting dokumen ini.</p>
            <button class="button-primary mt-4 w-full justify-center" type="submit">Setujui dan izinkan posting</button>
        </form>
        <form action="<?= site_url($url . '/reject') ?>" method="post">
            <?= csrf_field() ?><label class="form-label" for="review-notes">Kembalikan untuk perbaikan</label>
            <textarea class="form-input min-h-24" id="review-notes" name="review_notes" minlength="5" maxlength="500" required><?= esc(old('review_notes')) ?></textarea>
            <p class="mt-1 text-xs text-ink-muted">Jelaskan item atau biaya yang perlu diperbaiki.</p>
            <button class="button-secondary mt-3 w-full justify-center" type="submit">Kirim catatan perbaikan</button>
        </form>
    </div>
</section>
<?php endif ?>

<?php if ($canPost): ?>
<section class="panel-card mb-8 flex flex-col gap-3 border-brand-cocoa p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
    <div><h2 class="font-bold">Penerimaan telah disetujui</h2><p class="mt-1 max-w-2xl text-sm text-ink-muted">Posting akan menambah stok, memperbarui nilai persediaan, dan mencatat jurnal sesuai sumber pembayaran.</p></div>
    <?= panel_post('panel/inventory/receipts/post', ['receipt_id' => $receipt['id']], 'Posting penerimaan', 'button-primary shrink-0', 'Posting ' . $receipt['reference_number'] . '? Stok dan jurnal akan diperbarui sesuai biaya yang sudah disetujui.') ?>
</section>
<?php endif ?>
<?= $this->endSection() ?>
