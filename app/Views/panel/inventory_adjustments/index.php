<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$typeLabels = ['opening' => 'Saldo awal', 'damage' => 'Kerusakan', 'correction' => 'Koreksi', 'reversal' => 'Pembalik'];
$statusLabels = ['draft' => 'Draft', 'submitted' => 'Menunggu owner', 'approved' => 'Disetujui, siap posting', 'rejected' => 'Perlu perbaikan', 'posted' => 'Diposting', 'cancelled' => 'Dibatalkan'];
$statusStyles = ['draft' => 'bg-surface-soft-yellow text-ink-muted', 'submitted' => 'bg-[#FFFCF4] text-status-warning', 'approved' => 'bg-[#F0FDF4] text-status-success', 'rejected' => 'bg-[#FEF2F2] text-status-danger', 'posted' => 'bg-[#F0FDF4] text-status-success', 'cancelled' => 'bg-surface-soft-yellow text-ink-muted'];
$rows = array_map(static fn (array $adjustment): array => [
    '<strong>' . esc($adjustment['reference_number']) . '</strong>',
    esc(date('d/m/Y', strtotime($adjustment['document_date']))),
    esc($typeLabels[$adjustment['adjustment_type']] ?? $adjustment['adjustment_type']),
    esc($adjustment['reason']),
    panel_rupiah($adjustment['total_value']),
    '<span class="status-label ' . ($statusStyles[$adjustment['status']] ?? 'bg-surface-soft-yellow') . '">' . esc($statusLabels[$adjustment['status']] ?? 'Status tidak dikenal') . '</span>',
    panel_actions(['links' => ['Buka dokumen' => 'panel/inventory/adjustments/' . $adjustment['id']]]),
], $adjustments);
?>
<header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div><p class="section-kicker">Kartu stok</p><h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Saldo awal dan koreksi</h1><p class="mt-3 max-w-3xl text-ink-muted">Catat saldo awal, kerusakan, atau koreksi dengan alasan. Owner meninjau sebelum jumlah dan nilai persediaan berubah.</p></div>
    <a class="button-primary justify-center" href="<?= site_url('panel/inventory/adjustments/new') ?>">Buat penyesuaian</a>
</header>
<div class="mb-6 border border-brand-cocoa bg-[#FFFCF4] p-4 text-sm leading-6"><strong>Riwayat terjaga.</strong> Dokumen yang sudah diposting tidak dapat diedit. Jika ada kekeliruan, owner membuat dokumen pembalik yang merujuk transaksi asal.</div>
<?= panel_table($isOwner ? 'Semua penyesuaian stok' : 'Penyesuaian yang saya buat', ['Referensi', 'Tanggal', 'Jenis', 'Alasan', '>Nilai perubahan', 'Status', '>Aksi'], $rows, ['width' => '68rem', 'empty' => 'Belum ada penyesuaian stok.']) ?>
<?= $this->endSection() ?>
