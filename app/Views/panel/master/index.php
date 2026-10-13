<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$lineText = static fn (array $lines): string => $lines === [] ? '-' : esc(implode(', ', array_map(static fn (array $l): string => $l['item_name'] . ' ' . panel_qty($l['quantity']) . ' ' . $l['base_unit'], $lines)));
$crud = static fn (string $entity, array $row, string $route, string $field, string $label, string $name): string => panel_actions([
    'edit' => 'panel/data/' . $entity . '/' . $row['id'] . '/edit',
    'toggle' => ['url' => 'panel/master/' . $route . '/status', 'field' => $field, 'id' => $row['id'], 'active' => (bool) $row['is_active'], 'label' => $label],
    'delete' => ['url' => 'panel/master/' . $route . '/delete', 'field' => $field, 'id' => $row['id'], 'name' => $name, 'label' => $label],
]);

$itemRows = array_map(static function (array $i) use ($conversions, $crud): array {
    $own = array_filter($conversions, static fn (array $c): bool => (int) $c['inventory_item_id'] === (int) $i['id']);
    $low = (float) $i['quantity_on_hand'] < (float) $i['minimum_stock'];

    return [
        '<strong>' . esc($i['name']) . '</strong>', esc($i['sku']), esc($i['base_unit']),
        $low ? '<span class="font-bold text-status-danger">' . panel_qty($i['quantity_on_hand']) . '</span>' : panel_qty($i['quantity_on_hand']),
        panel_qty($i['minimum_stock']),
        $own === [] ? '-' : implode('<br>', array_map(static fn (array $c): string => '1 ' . esc($c['purchase_unit']) . ' = ' . panel_qty($c['factor_to_base']) . ' ' . esc($i['base_unit']), $own)),
        panel_badge((bool) $i['is_active']), $crud('item', $i, 'items', 'item_id', 'bahan', $i['name']),
    ];
}, $items);

$recipeRows = array_map(static fn (array $v): array => [
    '<strong>' . esc($v['product_name']) . '</strong>', esc($v['variant_name']), esc($v['sku']),
    ($lines = $recipesByVariant[$v['id']] ?? []) === [] ? '<span class="text-ink-muted">Belum ada bahan</span>' : $lineText($lines),
    panel_actions(['primary' => ['Atur resep' => 'panel/master/recipes/' . $v['id']]]),
], $variants);

$toppingRows = array_map(static fn (array $t): array => [
    '<strong>' . esc($t['name']) . '</strong>', esc($t['sku']), panel_rupiah($t['additional_price']),
    $lineText($toppingItemsByTopping[$t['id']] ?? []), panel_badge((bool) $t['is_active']), $crud('topping', $t, 'toppings', 'topping_id', 'topping', $t['name']),
], $toppings);

$supplierRows = array_map(static fn (array $s): array => [
    '<strong>' . esc($s['name']) . '</strong>', esc($s['contact_name'] ?: '-'), esc($s['phone'] ?: '-'), esc($s['address'] ?: '-'),
    panel_badge((bool) $s['is_active']), $crud('supplier', $s, 'suppliers', 'supplier_id', 'supplier', $s['name']),
], $suppliers);
?>
<div class="mb-6">
    <p class="section-kicker">Master persediaan</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Bahan, resep, dan supplier</h1>
</div>

<?= panel_table('Semua data bahan', ['Nama bahan', 'SKU', 'Satuan', '>Stok saat ini', '>Stok minimum', 'Konversi pembelian', 'Status', '>Aksi'], $itemRows, ['createUrl' => 'panel/data/item/create', 'width' => '60rem', 'empty' => 'Belum ada bahan.']) ?>
<?= panel_table('Semua data resep', ['Produk', 'Varian', 'SKU', 'Bahan resep', '>Aksi'], $recipeRows, ['empty' => 'Belum ada varian dengan mode resep.']) ?>
<?= panel_table('Semua data topping', ['Nama topping', 'SKU', '>Harga tambahan', 'Bahan', 'Status', '>Aksi'], $toppingRows, ['createUrl' => 'panel/data/topping/create', 'width' => '52rem', 'empty' => 'Belum ada topping.']) ?>
<?= panel_table('Semua data supplier', ['Nama supplier', 'Kontak', 'Telepon', 'Alamat', 'Status', '>Aksi'], $supplierRows, ['createUrl' => 'panel/data/supplier/create', 'width' => '52rem', 'empty' => 'Belum ada supplier.']) ?>
<?= $this->endSection() ?>
