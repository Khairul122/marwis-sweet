<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$productRows = array_map(static function (array $p) use ($variantsByProduct, $imagesByProduct): array {
    $variants = $variantsByProduct[$p['id']] ?? [];
    $prices = array_map('floatval', array_column($variants, 'price'));
    $image = ($imagesByProduct[$p['id']] ?? [])[0] ?? null;

    return [
        esc($variants[0]['sku'] ?? '-'),
        '<strong>' . esc($p['name']) . '</strong>' . ($p['is_featured'] ? ' <span class="status-label bg-brand-sun">Unggulan</span>' : ''),
        esc($p['category_name']),
        $p['product_type'] === 'prepared' ? 'Dari resep' : 'Siap jual',
        $prices === [] ? '-' : (min($prices) === max($prices) ? panel_rupiah(min($prices)) : panel_rupiah(min($prices)) . ' - ' . panel_rupiah(max($prices))),
        $image ? '<img class="h-10 w-14 border border-border-warm object-cover" src="' . base_url($image['image_path']) . '" alt="' . esc($image['alt_text']) . '" loading="lazy">' : '-',
        panel_badge((bool) $p['is_active']),
        panel_actions([
            'edit' => 'panel/data/product/' . $p['id'] . '/edit',
            'toggle' => ['url' => 'panel/catalog/products/status', 'field' => 'product_id', 'id' => $p['id'], 'active' => (bool) $p['is_active'], 'label' => 'produk'],
            'delete' => ['url' => 'panel/catalog/products/delete', 'field' => 'product_id', 'id' => $p['id'], 'name' => $p['name'], 'label' => 'produk'],
        ]),
    ];
}, $products);

$categoryRows = array_map(static fn (array $c): array => [
    '<strong>' . esc($c['name']) . '</strong>',
    esc($c['description'] ?: '-'),
    (string) (int) $c['sort_order'],
    panel_badge((bool) $c['is_active']),
    panel_actions([
        'edit' => 'panel/data/category/' . $c['id'] . '/edit',
        'toggle' => ['url' => 'panel/catalog/categories/status', 'field' => 'category_id', 'id' => $c['id'], 'active' => (bool) $c['is_active'], 'label' => 'kategori'],
        'delete' => ['url' => 'panel/catalog/categories/delete', 'field' => 'category_id', 'id' => $c['id'], 'name' => $c['name'], 'label' => 'kategori'],
    ]),
], $categories);
?>
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="section-kicker">Master usaha</p>
        <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Produk dan kategori</h1>
    </div>
    <a class="button-secondary self-start" href="<?= site_url('/') ?>" target="_blank" rel="noopener">Buka katalog publik</a>
</div>

<?= panel_table('Semua data produk', ['Kode', 'Nama produk', 'Kategori', 'Jenis', '>Harga', 'Foto', 'Status', '>Aksi'], $productRows, ['createUrl' => 'panel/data/product/create', 'width' => '56rem', 'empty' => 'Belum ada produk.']) ?>
<?= panel_table('Semua data kategori', ['Nama kategori', 'Keterangan', 'Urutan', 'Status', '>Aksi'], $categoryRows, ['createUrl' => 'panel/data/category/create', 'empty' => 'Belum ada kategori.']) ?>
<?= $this->endSection() ?>
