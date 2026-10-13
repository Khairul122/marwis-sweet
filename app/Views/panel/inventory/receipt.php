<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$url = 'panel/inventory/receipts/' . (int) $receipt['id'];
$isDraft = $receipt['status'] === 'draft';
$total = array_sum(array_map(static fn (array $l): float => (float) $l['line_total'], $lines));
$lineRows = array_map(static fn (array $l): array => [
    '<strong>' . esc($l['item_name']) . '</strong>', panel_qty($l['purchase_quantity']) . ' ' . esc($l['purchase_unit']), panel_qty($l['base_quantity']) . ' ' . esc($l['base_unit']),
    panel_rupiah($l['purchase_unit_cost']), panel_rupiah($l['line_total']),
], $lines);
?>
<nav class="mb-6 text-sm" aria-label="Breadcrumb"><a class="font-bold underline decoration-brand-sun decoration-2 underline-offset-4" href="<?= site_url('panel/inventory') ?>">&larr; Kembali ke daftar</a></nav>
<div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="section-kicker"><?= $isDraft ? 'Draft penerimaan' : 'Sudah diposting' ?></p>
        <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl"><?= esc($receipt['reference_number']) ?></h1>
        <p class="mt-3 text-ink-muted"><?= esc(date('d/m/Y', strtotime($receipt['document_date']))) ?> &middot; <?= esc($receipt['supplier_name'] ?: 'Tanpa supplier') ?> &middot; dibuat oleh <?= esc($receipt['creator_name']) ?><?= $receipt['notes'] ? ' &middot; ' . esc($receipt['notes']) : '' ?></p>
    </div>
    <p class="text-left sm:text-right"><span class="block text-sm text-ink-muted">Total penerimaan</span><span class="text-2xl font-black tabular-nums"><?= panel_rupiah($total) ?></span></p>
</div>

<?= panel_table('Item penerimaan', ['Bahan', '>Pembelian', '>Masuk stok', '>Biaya per satuan', '>Total'], $lineRows, ['search' => false, 'width' => '44rem', 'empty' => 'Belum ada item. Tambahkan bahan yang diterima di bawah.']) ?>

<?php if ($isDraft): ?>
<section class="panel-card -mt-4 mb-8 p-5 sm:p-6">
    <h2 class="mb-4 font-bold uppercase tracking-wide">Tambah item</h2>
    <form action="<?= site_url('panel/inventory/receipts/items') ?>" method="post" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
        <?= csrf_field() ?><input type="hidden" name="receipt_id" value="<?= (int) $receipt['id'] ?>"><input type="hidden" name="_return" value="<?= esc($url) ?>">
        <div><label class="form-label" for="item">Bahan</label><select class="form-input" id="item" name="item_id" required><option value="">Pilih bahan</option><?php foreach ($items as $item): ?><option value="<?= (int) $item['id'] ?>"><?= esc($item['name']) ?> (<?= esc($item['base_unit']) ?>)</option><?php endforeach ?></select></div>
        <div><label class="form-label" for="qty">Jumlah beli</label><input class="form-input" id="qty" name="purchase_quantity" type="number" min="0.001" step="0.001" value="<?= esc(old('purchase_quantity')) ?>" required></div>
        <div><label class="form-label" for="unit">Satuan beli</label><input class="form-input" id="unit" name="purchase_unit" placeholder="kg, liter, pcs" value="<?= esc(old('purchase_unit')) ?>" required></div>
        <div><label class="form-label" for="cost">Biaya per satuan</label><input class="form-input" id="cost" name="purchase_unit_cost" type="number" min="0" step="0.01" value="<?= esc(old('purchase_unit_cost')) ?>" required></div>
        <button class="button-secondary" type="submit">Simpan item</button>
    </form>
    <div class="mt-5 border-t border-border-warm pt-5">
        <?= $lines === []
            ? '<button class="button-primary cursor-not-allowed opacity-50" type="button" disabled aria-disabled="true">Posting penerimaan</button><p class="mt-2 text-sm text-ink-muted">Tambahkan minimal satu item sebelum posting.</p>'
            : panel_post('panel/inventory/receipts/post', ['receipt_id' => $receipt['id'], '_return' => 'panel/inventory'], 'Posting penerimaan', 'button-primary', 'Posting penerimaan akan menambah stok dan mengunci dokumen. Lanjutkan?') ?>
    </div>
</section>
<?php endif ?>
<?= $this->endSection() ?>
