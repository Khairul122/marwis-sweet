<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$rupiah = static fn ($value): string => 'Rp' . number_format((float) $value, 0, ',', '.');
$activeBadge = static fn (bool $active): string => $active ? '<span class="status-label bg-[#F0FDF4] text-status-success">Aktif</span>' : '<span class="status-label bg-surface-soft-yellow text-ink-muted">Nonaktif</span>';
?>
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="section-kicker">Master usaha</p>
        <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Produk dan kategori</h1>
    </div>
    <a class="button-secondary self-start" href="<?= site_url('/') ?>" target="_blank" rel="noopener">Buka katalog publik</a>
</div>

<section class="panel-card mb-8 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-warm px-5 py-4">
        <h2 class="font-bold uppercase tracking-wide">Semua data produk</h2>
        <a class="button-primary" href="<?= site_url('panel/data/product/create') ?>">Tambah data</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[56rem] text-left text-sm" data-datatable>
            <thead class="bg-surface-soft-yellow"><tr>
                <th class="px-4 py-3 font-bold">No</th><th class="px-4 py-3 font-bold">Kode</th><th class="px-4 py-3 font-bold">Nama produk</th><th class="px-4 py-3 font-bold">Kategori</th><th class="px-4 py-3 font-bold">Jenis</th><th class="px-4 py-3 text-right font-bold">Harga</th><th class="px-4 py-3 font-bold">Foto</th><th class="px-4 py-3 font-bold">Status</th><th class="px-4 py-3 text-right font-bold">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-border-warm">
                <?php foreach ($products as $i => $product): ?>
                    <?php $variants = $variantsByProduct[$product['id']] ?? []; $prices = array_map('floatval', array_column($variants, 'price')); $images = $imagesByProduct[$product['id']] ?? []; ?>
                    <tr>
                        <td class="px-4 py-3 tabular-nums" data-dt-no><?= $i + 1 ?></td>
                        <td class="px-4 py-3"><?= esc($variants[0]['sku'] ?? '-') ?></td>
                        <td class="px-4 py-3 font-semibold"><?= esc($product['name']) ?><?= $product['is_featured'] ? ' <span class="status-label bg-brand-sun">Unggulan</span>' : '' ?></td>
                        <td class="px-4 py-3"><?= esc($product['category_name']) ?></td>
                        <td class="px-4 py-3"><?= $product['product_type'] === 'prepared' ? 'Dari resep' : 'Siap jual' ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= $prices === [] ? '-' : (min($prices) === max($prices) ? $rupiah(min($prices)) : $rupiah(min($prices)) . ' - ' . $rupiah(max($prices))) ?></td>
                        <td class="px-4 py-3"><?php if ($images !== []): ?><img class="h-10 w-14 border border-border-warm object-cover" src="<?= base_url($images[0]['image_path']) ?>" alt="<?= esc($images[0]['alt_text']) ?>" loading="lazy"><?php else: ?><span class="text-ink-muted">-</span><?php endif ?></td>
                        <td class="px-4 py-3"><?= $activeBadge((bool) $product['is_active']) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <a class="button-secondary button-sm" href="<?= site_url('panel/data/product/' . (int) $product['id'] . '/edit') ?>">Edit</a>
                                <form action="<?= site_url('panel/catalog/products/status') ?>" method="post" data-confirm-form="<?= $product['is_active'] ? 'Sembunyikan' : 'Tampilkan' ?> produk ini di katalog?"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button class="button-secondary button-sm" type="submit"><?= $product['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                                <form action="<?= site_url('panel/catalog/products/delete') ?>" method="post" data-confirm-form="Hapus produk &quot;<?= esc($product['name']) ?>&quot; beserta varian dan fotonya? Tindakan ini tidak dapat dibatalkan."><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button class="button-danger" type="submit">Hapus</button></form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($products === []): ?><tr><td class="px-4 py-6 text-ink-muted" colspan="9">Belum ada produk. Pilih "Tambah data" untuk memulai.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel-card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-warm px-5 py-4">
        <h2 class="font-bold uppercase tracking-wide">Semua data kategori</h2>
        <a class="button-primary" href="<?= site_url('panel/data/category/create') ?>">Tambah data</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[40rem] text-left text-sm" data-datatable>
            <thead class="bg-surface-soft-yellow"><tr>
                <th class="px-4 py-3 font-bold">No</th><th class="px-4 py-3 font-bold">Nama kategori</th><th class="px-4 py-3 font-bold">Keterangan</th><th class="px-4 py-3 font-bold">Urutan</th><th class="px-4 py-3 font-bold">Status</th><th class="px-4 py-3 text-right font-bold">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-border-warm">
                <?php foreach ($categories as $i => $category): ?>
                    <tr>
                        <td class="px-4 py-3 tabular-nums" data-dt-no><?= $i + 1 ?></td>
                        <td class="px-4 py-3 font-semibold"><?= esc($category['name']) ?></td>
                        <td class="px-4 py-3"><?= esc($category['description'] ?: '-') ?></td>
                        <td class="px-4 py-3 tabular-nums"><?= (int) $category['sort_order'] ?></td>
                        <td class="px-4 py-3"><?= $activeBadge((bool) $category['is_active']) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <a class="button-secondary button-sm" href="<?= site_url('panel/data/category/' . (int) $category['id'] . '/edit') ?>">Edit</a>
                                <form action="<?= site_url('panel/catalog/categories/status') ?>" method="post" data-confirm-form="<?= $category['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> kategori ini?"><?= csrf_field() ?><input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>"><button class="button-secondary button-sm" type="submit"><?= $category['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                                <form action="<?= site_url('panel/catalog/categories/delete') ?>" method="post" data-confirm-form="Hapus kategori &quot;<?= esc($category['name']) ?>&quot;? Tindakan ini tidak dapat dibatalkan."><?= csrf_field() ?><input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>"><button class="button-danger" type="submit">Hapus</button></form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($categories === []): ?><tr><td class="px-4 py-6 text-ink-muted" colspan="6">Belum ada kategori.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
