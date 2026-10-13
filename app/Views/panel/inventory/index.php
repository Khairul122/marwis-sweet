<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$stockRows = array_map(static fn (array $i): array => [
    '<strong>' . esc($i['name']) . '</strong>', esc($i['sku']), panel_qty($i['quantity_on_hand']) . ' ' . esc($i['base_unit']), panel_qty($i['minimum_stock']) . ' ' . esc($i['base_unit']),
    ...($isOwner ? [number_format((float) $i['average_cost'], 2, ',', '.')] : []),
    (float) $i['quantity_on_hand'] <= (float) $i['minimum_stock'] ? '<span class="status-label bg-[#FFF7ED] text-status-warning">Perlu restok</span>' : '<span class="status-label bg-[#F0FDF4] text-status-success">Stok aman</span>',
], $items);

$receiptLabels = [
    'draft' => ['Draft', 'bg-surface-soft-yellow text-ink-muted', 'Isi draft'],
    'submitted' => ['Menunggu owner', 'bg-[#FFFCF4] text-status-warning', 'Tinjau biaya'],
    'approved' => ['Disetujui, siap posting', 'bg-[#F0FDF4] text-status-success', 'Lihat penerimaan'],
    'rejected' => ['Perlu perbaikan', 'bg-[#FEF2F2] text-status-danger', 'Perbaiki draft'],
    'posted' => ['Diposting', 'bg-[#F0FDF4] text-status-success', 'Lihat penerimaan'],
    'cancelled' => ['Dibatalkan', 'bg-surface-soft-yellow text-ink-muted', 'Lihat penerimaan'],
];
$receiptRows = array_map(static fn (array $r): array => [
    '<strong>' . esc($r['reference_number']) . '</strong>', esc(date('d/m/Y', strtotime($r['document_date']))), esc($r['supplier_name'] ?: '-'), esc($r['creator_name']),
    panel_rupiah($r['total']),
    '<span class="status-label ' . ($receiptLabels[$r['status']][1] ?? 'bg-surface-soft-yellow') . '">' . esc($receiptLabels[$r['status']][0] ?? 'Status tidak dikenal') . '</span>',
    panel_actions(['links' => [$receiptLabels[$r['status']][2] ?? 'Lihat' => 'panel/inventory/receipts/' . $r['id']]]),
], $receipts);

$types = ['receipt' => 'Penerimaan', 'sale' => 'Penjualan', 'opname' => 'Opname', 'return' => 'Retur'];
$moveRows = array_map(static fn (array $m): array => [
    esc(date('d/m/Y H:i', strtotime($m['posted_at']))), '<strong>' . esc($m['item_name']) . '</strong>', esc($types[$m['movement_type']] ?? $m['movement_type']),
    ((float) $m['quantity'] > 0 ? '+' : '') . panel_qty($m['quantity']) . ' ' . esc($m['base_unit']), ...($isOwner ? [panel_rupiah($m['total_cost'])] : []), esc($m['notes'] ?: '-'),
], $movements);
$stockHead = ['Bahan', 'SKU', '>Stok', '>Minimum'];
if ($isOwner) { $stockHead[] = '>Biaya rata-rata (Rp)'; }
$stockHead[] = 'Status';
$movementHead = ['Waktu', 'Bahan', 'Jenis', '>Kuantitas'];
if ($isOwner) { $movementHead[] = '>Nilai'; }
$movementHead[] = 'Referensi';
?>
<div class="mb-6">
    <p class="section-kicker">Operasional stok</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Penerimaan dan kartu stok</h1>
    <p class="mt-3 max-w-3xl text-ink-muted"><?= $isOwner
        ? 'Tinjau biaya yang diajukan kasir. Stok dan jurnal baru berubah setelah penerimaan yang disetujui diposting.'
        : 'Lihat posisi stok, susun draft penerimaan, lalu minta persetujuan owner sebelum mem-posting.' ?></p>
</div>

<div class="mb-6 flex flex-col gap-3 border border-brand-cocoa bg-[#FFFCF4] p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
    <p class="max-w-2xl text-sm leading-6">Persetujuan memeriksa bahan, jumlah, dan biaya. Pengajuan yang belum disetujui tidak masuk ke saldo stok maupun jurnal.</p>
    <a class="button-primary justify-center" href="<?= site_url('panel/inventory/receipts/new') ?>">Buat draft penerimaan</a>
</div>
<?= panel_table($isOwner ? 'Penerimaan untuk diperiksa' : 'Draft dan penerimaan saya', ['Nomor referensi', 'Tanggal', 'Supplier', 'Dibuat oleh', '>Total', 'Status', '>Aksi'], $receiptRows, ['width' => '54rem', 'empty' => 'Belum ada penerimaan. Buat draft untuk mencatat barang yang diterima.']) ?>
<?= panel_table('Posisi stok bahan', $stockHead, $stockRows, ['width' => '48rem', 'empty' => 'Belum ada bahan aktif.']) ?>
<?= panel_table('Kartu stok terbaru', $movementHead, $moveRows, ['width' => '48rem', 'empty' => 'Belum ada mutasi stok.']) ?>
<?= $this->endSection() ?>
