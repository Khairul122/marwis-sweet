<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$badge = static fn (bool $active): string => $active ? '<span class="status-label bg-[#F0FDF4] text-status-success">Aktif</span>' : '<span class="status-label bg-surface-soft-yellow text-ink-muted">Nonaktif</span>';
$qty = static fn ($value): string => rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
?>
<div class="mb-6">
    <p class="section-kicker">Master persediaan</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Bahan, resep, dan supplier</h1>
</div>

<section class="panel-card mb-8 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-warm px-5 py-4">
        <h2 class="font-bold uppercase tracking-wide">Semua data bahan</h2>
        <a class="button-primary" href="<?= site_url('panel/data/item/create') ?>">Tambah data</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[60rem] text-left text-sm" data-datatable>
            <thead class="bg-surface-soft-yellow"><tr><th class="px-4 py-3 font-bold">No</th><th class="px-4 py-3 font-bold">Nama bahan</th><th class="px-4 py-3 font-bold">SKU</th><th class="px-4 py-3 font-bold">Satuan</th><th class="px-4 py-3 font-bold text-right">Stok saat ini</th><th class="px-4 py-3 font-bold text-right">Stok minimum</th><th class="px-4 py-3 font-bold">Konversi pembelian</th><th class="px-4 py-3 font-bold">Status</th><th class="px-4 py-3 font-bold text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-border-warm">
                <?php foreach ($items as $i => $item): ?>
                    <?php $itemConversions = array_filter($conversions, static fn ($c) => (int) $c['inventory_item_id'] === (int) $item['id']); ?>
                    <tr>
                        <td class="px-4 py-3 tabular-nums" data-dt-no><?= $i + 1 ?></td>
                        <td class="px-4 py-3 font-semibold"><?= esc($item['name']) ?></td>
                        <td class="px-4 py-3"><?= esc($item['sku']) ?></td>
                        <td class="px-4 py-3"><?= esc($item['base_unit']) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums <?= (float) $item['quantity_on_hand'] < (float) $item['minimum_stock'] ? 'font-bold text-status-danger' : '' ?>"><?= $qty($item['quantity_on_hand']) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= $qty($item['minimum_stock']) ?></td>
                        <td class="px-4 py-3"><?php foreach ($itemConversions as $c): ?><span class="block">1 <?= esc($c['purchase_unit']) ?> = <?= $qty($c['factor_to_base']) ?> <?= esc($item['base_unit']) ?></span><?php endforeach ?><?= $itemConversions === [] ? '-' : '' ?></td>
                        <td class="px-4 py-3"><?= $badge((bool) $item['is_active']) ?></td>
                        <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <a class="button-secondary button-sm" href="<?= site_url('panel/data/item/' . (int) $item['id'] . '/edit') ?>">Edit</a>
                            <form action="<?= site_url('panel/master/items/status') ?>" method="post" data-confirm-form="<?= $item['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> bahan ini?"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><button class="button-secondary button-sm" type="submit"><?= $item['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                            <form action="<?= site_url('panel/master/items/delete') ?>" method="post" data-confirm-form="Hapus bahan &quot;<?= esc($item['name']) ?>&quot;? Tindakan ini tidak dapat dibatalkan."><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><button class="button-danger" type="submit">Hapus</button></form>
                        </div>
                    </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($items === []): ?><tr><td class="px-4 py-6 text-ink-muted" colspan="9">Belum ada bahan.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel-card mb-8 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-warm px-5 py-4">
        <h2 class="font-bold uppercase tracking-wide">Semua data resep</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[48rem] text-left text-sm" data-datatable>
            <thead class="bg-surface-soft-yellow"><tr><th class="px-4 py-3 font-bold">No</th><th class="px-4 py-3 font-bold">Produk</th><th class="px-4 py-3 font-bold">Varian</th><th class="px-4 py-3 font-bold">SKU</th><th class="px-4 py-3 font-bold">Bahan resep</th><th class="px-4 py-3 font-bold text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-border-warm">
                <?php foreach ($variants as $i => $variant): ?>
                    <?php $lines = $recipesByVariant[$variant['id']] ?? []; ?>
                    <tr>
                        <td class="px-4 py-3 tabular-nums" data-dt-no><?= $i + 1 ?></td>
                        <td class="px-4 py-3 font-semibold"><?= esc($variant['product_name']) ?></td>
                        <td class="px-4 py-3"><?= esc($variant['variant_name']) ?></td>
                        <td class="px-4 py-3"><?= esc($variant['sku']) ?></td>
                        <td class="px-4 py-3"><?= $lines === [] ? '<span class="text-ink-muted">Belum ada bahan</span>' : esc(implode(', ', array_map(static fn ($l) => $l['item_name'] . ' ' . rtrim(rtrim(number_format((float) $l['quantity'], 3, ',', '.'), '0'), ',') . ' ' . $l['base_unit'], $lines))) ?></td>
                        <td class="px-4 py-3 text-right"><a class="button-primary button-sm" href="<?= site_url('panel/master/recipes/' . (int) $variant['id']) ?>">Atur resep</a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($variants === []): ?><tr><td class="px-4 py-6 text-ink-muted" colspan="6">Belum ada varian dengan mode resep.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel-card mb-8 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-warm px-5 py-4">
        <h2 class="font-bold uppercase tracking-wide">Semua data topping</h2>
        <a class="button-primary" href="<?= site_url('panel/data/topping/create') ?>">Tambah data</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[52rem] text-left text-sm" data-datatable>
            <thead class="bg-surface-soft-yellow"><tr><th class="px-4 py-3 font-bold">No</th><th class="px-4 py-3 font-bold">Nama topping</th><th class="px-4 py-3 font-bold">SKU</th><th class="px-4 py-3 font-bold text-right">Harga tambahan</th><th class="px-4 py-3 font-bold">Bahan</th><th class="px-4 py-3 font-bold">Status</th><th class="px-4 py-3 font-bold text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-border-warm">
                <?php foreach ($toppings as $i => $topping): ?>
                    <?php $lines = $toppingItemsByTopping[$topping['id']] ?? []; ?>
                    <tr>
                        <td class="px-4 py-3 tabular-nums" data-dt-no><?= $i + 1 ?></td>
                        <td class="px-4 py-3 font-semibold"><?= esc($topping['name']) ?></td>
                        <td class="px-4 py-3"><?= esc($topping['sku']) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums">Rp<?= number_format((float) $topping['additional_price'], 0, ',', '.') ?></td>
                        <td class="px-4 py-3"><?= $lines === [] ? '-' : esc(implode(', ', array_map(static fn ($l) => $l['item_name'] . ' ' . rtrim(rtrim(number_format((float) $l['quantity'], 3, ',', '.'), '0'), ',') . ' ' . $l['base_unit'], $lines))) ?></td>
                        <td class="px-4 py-3"><?= $badge((bool) $topping['is_active']) ?></td>
                        <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <a class="button-secondary button-sm" href="<?= site_url('panel/data/topping/' . (int) $topping['id'] . '/edit') ?>">Edit</a>
                            <form action="<?= site_url('panel/master/toppings/status') ?>" method="post" data-confirm-form="<?= $topping['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> topping ini?"><?= csrf_field() ?><input type="hidden" name="topping_id" value="<?= (int) $topping['id'] ?>"><button class="button-secondary button-sm" type="submit"><?= $topping['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                            <form action="<?= site_url('panel/master/toppings/delete') ?>" method="post" data-confirm-form="Hapus topping &quot;<?= esc($topping['name']) ?>&quot;? Tindakan ini tidak dapat dibatalkan."><?= csrf_field() ?><input type="hidden" name="topping_id" value="<?= (int) $topping['id'] ?>"><button class="button-danger" type="submit">Hapus</button></form>
                        </div>
                    </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($toppings === []): ?><tr><td class="px-4 py-6 text-ink-muted" colspan="7">Belum ada topping.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel-card mb-8 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-warm px-5 py-4">
        <h2 class="font-bold uppercase tracking-wide">Semua data supplier</h2>
        <a class="button-primary" href="<?= site_url('panel/data/supplier/create') ?>">Tambah data</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[52rem] text-left text-sm" data-datatable>
            <thead class="bg-surface-soft-yellow"><tr><th class="px-4 py-3 font-bold">No</th><th class="px-4 py-3 font-bold">Nama supplier</th><th class="px-4 py-3 font-bold">Kontak</th><th class="px-4 py-3 font-bold">Telepon</th><th class="px-4 py-3 font-bold">Alamat</th><th class="px-4 py-3 font-bold">Status</th><th class="px-4 py-3 font-bold text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-border-warm">
                <?php foreach ($suppliers as $i => $supplier): ?>
                    <tr>
                        <td class="px-4 py-3 tabular-nums" data-dt-no><?= $i + 1 ?></td>
                        <td class="px-4 py-3 font-semibold"><?= esc($supplier['name']) ?></td>
                        <td class="px-4 py-3"><?= esc($supplier['contact_name'] ?: '-') ?></td>
                        <td class="px-4 py-3"><?= esc($supplier['phone'] ?: '-') ?></td>
                        <td class="px-4 py-3"><?= esc($supplier['address'] ?: '-') ?></td>
                        <td class="px-4 py-3"><?= $badge((bool) $supplier['is_active']) ?></td>
                        <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <a class="button-secondary button-sm" href="<?= site_url('panel/data/supplier/' . (int) $supplier['id'] . '/edit') ?>">Edit</a>
                            <form action="<?= site_url('panel/master/suppliers/status') ?>" method="post" data-confirm-form="<?= $supplier['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> supplier ini?"><?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= (int) $supplier['id'] ?>"><button class="button-secondary button-sm" type="submit"><?= $supplier['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                            <form action="<?= site_url('panel/master/suppliers/delete') ?>" method="post" data-confirm-form="Hapus supplier &quot;<?= esc($supplier['name']) ?>&quot;? Tindakan ini tidak dapat dibatalkan."><?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= (int) $supplier['id'] ?>"><button class="button-danger" type="submit">Hapus</button></form>
                        </div>
                    </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($suppliers === []): ?><tr><td class="px-4 py-6 text-ink-muted" colspan="7">Belum ada supplier.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
