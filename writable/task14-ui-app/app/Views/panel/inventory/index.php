<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$stockRows = array_map(static fn (array $i): array => [
    '<strong>' . esc($i['name']) . '</strong>', esc($i['sku']), panel_qty($i['quantity_on_hand']) . ' ' . esc($i['base_unit']), panel_qty($i['minimum_stock']) . ' ' . esc($i['base_unit']),
    number_format((float) $i['average_cost'], 2, ',', '.'),
    (float) $i['quantity_on_hand'] <= (float) $i['minimum_stock'] ? '<span class="status-label bg-[#FFF7ED] text-status-warning">Perlu restok</span>' : '<span class="status-label bg-[#F0FDF4] text-status-success">Stok aman</span>',
], $items);

$receiptRows = array_map(static fn (array $r): array => [
    '<strong>' . esc($r['reference_number']) . '</strong>', esc(date('d/m/Y', strtotime($r['document_date']))), esc($r['supplier_name'] ?: '-'), esc($r['creator_name']),
    panel_rupiah($r['total']),
    $r['status'] === 'posted' ? '<span class="status-label bg-[#F0FDF4] text-status-success">Diposting</span>' : '<span class="status-label bg-surface-soft-yellow">Draft</span>',
    panel_actions(['links' => [$r['status'] === 'draft' ? 'Isi item' : 'Lihat' => 'panel/inventory/receipts/' . $r['id']]]),
], $receipts);

$types = ['receipt' => 'Penerimaan', 'sale' => 'Penjualan', 'opname' => 'Opname', 'return' => 'Retur'];
$moveRows = array_map(static fn (array $m): array => [
    esc(date('d/m/Y H:i', strtotime($m['posted_at']))), '<strong>' . esc($m['item_name']) . '</strong>', esc($types[$m['movement_type']] ?? $m['movement_type']),
    ((float) $m['quantity'] > 0 ? '+' : '') . panel_qty($m['quantity']) . ' ' . esc($m['base_unit']), panel_rupiah($m['total_cost']), esc($m['notes'] ?: '-'),
], $movements);
?>
<div class="mb-6">
    <p class="section-kicker">Operasional stok</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Penerimaan dan kartu stok</h1>
    <p class="mt-3 max-w-3xl text-ink-muted">Stok hanya berubah lewat penerimaan yang sudah diposting, penjualan, opname, dan retur.</p>
</div>

<?= panel_table('Semua data penerimaan', ['Nomor referensi', 'Tanggal', 'Supplier', 'Dibuat oleh', '>Total', 'Status', '>Aksi'], $receiptRows, ['createUrl' => 'panel/data/receipt/create', 'create' => 'Tambah penerimaan', 'width' => '54rem', 'empty' => 'Belum ada penerimaan.']) ?>
<?= panel_table('Posisi stok bahan', ['Bahan', 'SKU', '>Stok', '>Minimum', '>Biaya rata-rata (Rp)', 'Status'], $stockRows, ['width' => '48rem', 'empty' => 'Belum ada bahan aktif. Tambahkan di master bahan.']) ?>
<?= panel_table('Kartu stok terbaru', ['Waktu', 'Bahan', 'Jenis', '>Kuantitas', '>Nilai', 'Referensi'], $moveRows, ['width' => '48rem', 'empty' => 'Belum ada mutasi stok.']) ?>
<?= $this->endSection() ?>
