<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$returnUrl = 'panel/master/recipes/' . (int) $variant['id'];
$lineRows = array_map(static fn (array $l): array => [
    '<strong>' . esc($l['item_name']) . '</strong>', panel_qty($l['quantity']) . ' ' . esc($l['base_unit']),
    panel_post('panel/master/recipes/remove', ['recipe_item_id' => $l['id'], '_return' => $returnUrl], 'Hapus', 'button-danger', 'Hapus ' . $l['item_name'] . ' dari resep ini?'),
], $lines);
?>
<nav class="mb-6 text-sm" aria-label="Breadcrumb"><a class="font-bold underline decoration-brand-sun decoration-2 underline-offset-4" href="<?= site_url('panel/master') ?>">&larr; Kembali ke daftar</a></nav>
<div class="mb-8">
    <p class="section-kicker"><?= esc($variant['sku']) ?></p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Resep <?= esc($variant['product_name']) ?> &middot; <?= esc($variant['variant_name']) ?></h1>
    <p class="mt-3 text-ink-muted">Jumlah memakai satuan dasar bahan untuk satu porsi penjualan.</p>
</div>
<div class="max-w-3xl">
    <?= panel_table('Bahan resep', ['Bahan', '>Jumlah', '>Aksi'], $lineRows, ['search' => false, 'empty' => 'Belum ada bahan pada resep ini.', 'width' => '28rem']) ?>
    <?= view('panel/crud/_line_form', ['formItems' => $items, 'formUrl' => 'panel/master/recipes', 'formField' => 'variant_id', 'formId' => $variant['id'], 'returnUrl' => $returnUrl]) ?>
</div>
<?= $this->endSection() ?>
