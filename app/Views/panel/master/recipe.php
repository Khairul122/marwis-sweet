<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php $url = 'panel/master/recipes/' . (int) $variant['id']; ?>
<nav class="mb-6 text-sm" aria-label="Breadcrumb"><a class="font-bold underline decoration-brand-sun decoration-2 underline-offset-4" href="<?= site_url('panel/master') ?>">&larr; Kembali ke daftar</a></nav>
<div class="mb-8">
    <p class="section-kicker"><?= esc($variant['sku']) ?></p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Resep <?= esc($variant['product_name']) ?> &middot; <?= esc($variant['variant_name']) ?></h1>
    <p class="mt-3 text-ink-muted">Jumlah memakai satuan dasar bahan untuk satu porsi penjualan.</p>
</div>

<section class="panel-card max-w-3xl overflow-hidden">
    <div class="border-b border-border-warm px-5 py-4"><h2 class="font-bold uppercase tracking-wide">Bahan resep</h2></div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[28rem] text-left text-sm">
            <thead class="bg-surface-soft-yellow"><tr><th class="px-4 py-3 font-bold">No</th><th class="px-4 py-3 font-bold">Bahan</th><th class="px-4 py-3 text-right font-bold">Jumlah</th><th class="px-4 py-3 text-right font-bold">Aksi</th></tr></thead>
            <tbody class="divide-y divide-border-warm">
                <?php foreach ($lines as $i => $line): ?>
                    <tr><td class="px-4 py-3"><?= $i + 1 ?></td><td class="px-4 py-3 font-semibold"><?= esc($line['item_name']) ?></td><td class="px-4 py-3 text-right tabular-nums"><?= number_format((float) $line['quantity'], 3, ',', '.') ?> <?= esc($line['base_unit']) ?></td>
                        <td class="px-4 py-3 text-right"><form action="<?= site_url('panel/master/recipes/remove') ?>" method="post" data-confirm-form="Hapus <?= esc($line['item_name']) ?> dari resep ini?"><?= csrf_field() ?><input type="hidden" name="recipe_item_id" value="<?= (int) $line['id'] ?>"><input type="hidden" name="_return" value="<?= esc($url) ?>"><button class="button-danger" type="submit">Hapus</button></form></td></tr>
                <?php endforeach ?>
                <?php if ($lines === []): ?><tr><td class="px-4 py-6 text-ink-muted" colspan="4">Belum ada bahan pada resep ini.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
    <form action="<?= site_url('panel/master/recipes') ?>" method="post" class="grid gap-4 border-t border-border-warm p-5 sm:grid-cols-[1fr_9rem_auto] sm:items-end sm:p-6">
        <?= csrf_field() ?><input type="hidden" name="variant_id" value="<?= (int) $variant['id'] ?>"><input type="hidden" name="_return" value="<?= esc($url) ?>">
        <div><label class="form-label" for="r-item">Bahan</label><select class="form-input" id="r-item" name="item_id" required><option value="">Pilih bahan</option><?php foreach ($items as $item): ?><option value="<?= (int) $item['id'] ?>"><?= esc($item['name']) ?> (<?= esc($item['base_unit']) ?>)</option><?php endforeach ?></select></div>
        <div><label class="form-label" for="r-qty">Jumlah</label><input class="form-input" id="r-qty" name="quantity" type="number" min="0.001" step="0.001" required></div>
        <button class="button-primary" type="submit">Simpan bahan</button>
    </form>
</section>
<?= $this->endSection() ?>
