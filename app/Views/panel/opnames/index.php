<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$statusLabels = ['counting' => 'Sedang dihitung', 'submitted' => 'Menunggu owner', 'rejected' => 'Perlu dikoreksi', 'approved' => 'Sudah diposting', 'cancelled' => 'Dibatalkan'];
?>
<div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div><p class="section-kicker">Kontrol persediaan</p><h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Stok opname</h1><p class="mt-3 max-w-3xl text-ink-muted">Bekukan saldo item pada waktu cut-off, hitung stok fisik, lalu kirim selisih untuk persetujuan owner.</p></div>
    <a class="button-secondary self-start" href="<?= site_url('panel/inventory') ?>">Lihat kartu stok</a>
</div>

<section class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)] xl:items-start">
    <div class="panel-card p-5 sm:p-6 xl:sticky xl:top-24">
        <p class="section-kicker">Sesi baru</p>
        <h2 class="mt-2 font-display text-2xl">Pilih cakupan hitung</h2>
        <p class="mt-2 text-sm leading-6 text-ink-muted">Mutasi item terpilih akan dikunci segera setelah sesi dibuat.</p>
        <form action="<?= site_url('panel/opnames/create') ?>" method="post" class="mt-5 space-y-4">
            <?= csrf_field() ?>
            <fieldset><legend class="form-label">Cakupan</legend><div class="grid grid-cols-2 gap-2"><label class="flex min-h-12 items-center gap-2 border border-border-warm bg-surface-soft-yellow px-3 text-sm font-semibold"><input class="h-5 w-5 accent-brand-cocoa" type="radio" name="scope" value="all" checked data-opname-scope> Semua item</label><label class="flex min-h-12 items-center gap-2 border border-border-warm bg-white px-3 text-sm font-semibold"><input class="h-5 w-5 accent-brand-cocoa" type="radio" name="scope" value="partial" data-opname-scope> Sebagian</label></div></fieldset>
            <fieldset class="hidden border border-border-warm p-3" data-opname-partial><legend class="px-1 text-xs font-bold uppercase tracking-wider text-ink-muted">Item yang dihitung</legend><div class="mt-2 max-h-56 space-y-1 overflow-y-auto">
                <?php foreach ($inventoryItems as $inventoryItem): ?><label class="flex min-h-11 items-center gap-3 px-2 text-sm hover:bg-surface-soft-yellow"><input class="h-5 w-5 accent-brand-cocoa" type="checkbox" name="item_ids[]" value="<?= (int) $inventoryItem['id'] ?>"> <span><strong><?= esc($inventoryItem['name']) ?></strong><span class="ml-1 text-xs text-ink-muted"><?= esc($inventoryItem['base_unit']) ?></span></span></label><?php endforeach ?>
            </div></fieldset>
            <div><label class="form-label" for="opname-notes">Catatan sesi <span class="font-normal text-ink-muted">(opsional)</span></label><textarea class="form-input min-h-24" id="opname-notes" name="notes" maxlength="500"></textarea></div>
            <button class="button-primary w-full" type="submit">Mulai dan kunci stok</button>
        </form>
    </div>

    <div>
        <div class="mb-4"><h2 class="text-xl font-bold">Sesi opname</h2><p class="mt-1 text-sm text-ink-muted">Sesi yang belum selesai tetap ditampilkan di bagian atas.</p></div>
        <?php if ($opnames === []): ?>
            <div class="panel-card p-8"><p class="font-display text-2xl">Belum ada sesi opname.</p><p class="mt-2 text-ink-muted">Buat sesi pertama untuk mencocokkan saldo sistem dengan jumlah fisik.</p></div>
        <?php else: ?>
            <div class="space-y-5">
                <?php foreach ($opnames as $opname): ?>
                    <?php
                    $lines = $itemsByOpname[$opname['id']] ?? [];
                    $editable = in_array($opname['status'], ['counting', 'rejected'], true) && (($user['role'] ?? '') === 'owner' || (int) $opname['created_by'] === (int) session('user_id'));
                    $canCancel = in_array($opname['status'], ['counting', 'submitted', 'rejected'], true) && (($user['role'] ?? '') === 'owner' || (int) $opname['created_by'] === (int) session('user_id'));
                    $canViewEvidence = ($user['role'] ?? '') === 'owner' || (int) $opname['created_by'] === (int) session('user_id');
                    $hasMissing = false;
                    foreach ($lines as $line) { if ($line['physical_quantity'] === null) { $hasMissing = true; break; } }
                    ?>
                    <article class="panel-card overflow-hidden">
                        <header class="grid gap-4 p-5 sm:p-6 lg:grid-cols-[1fr_auto] lg:items-start">
                            <div><div class="flex flex-wrap items-center gap-2"><h3 class="font-display text-xl"><?= esc($opname['opname_number']) ?></h3><span class="status-label <?= $opname['status'] === 'approved' ? 'bg-[#F0FDF4] text-status-success' : ($opname['status'] === 'cancelled' ? 'bg-[#FEF2F2] text-status-danger' : 'bg-brand-sun') ?>"><?= esc($statusLabels[$opname['status']] ?? $opname['status']) ?></span></div><p class="mt-2 text-sm text-ink-muted">Cut-off <?= esc(date('d/m/Y H:i', strtotime($opname['cutoff_at']))) ?> · <?= count($lines) ?> item · <?= esc($opname['creator_name']) ?></p><?php if ($opname['notes']): ?><p class="mt-2 text-sm"><?= esc($opname['notes']) ?></p><?php endif ?></div>
                            <div class="lg:text-right"><p class="text-xs font-bold uppercase tracking-wider text-ink-muted">Cakupan</p><p class="mt-1 font-semibold"><?= $opname['scope'] === 'all' ? 'Semua item aktif' : 'Sebagian item' ?></p></div>
                        </header>

                        <?php if ($opname['status'] === 'rejected' && $opname['review_notes']): ?><div class="border-y border-status-danger bg-[#FEF2F2] px-5 py-4 text-sm text-status-danger"><strong>Perlu dikoreksi.</strong> <?= esc($opname['review_notes']) ?></div><?php endif ?>
                        <?php if ($opname['status'] === 'cancelled'): ?><div class="border-t border-border-warm bg-[#FEF2F2] px-5 py-4 text-sm text-status-danger"><strong>Alasan pembatalan:</strong> <?= esc($opname['cancellation_reason']) ?></div><?php endif ?>

                        <?php if ($editable || ($opname['evidence_path'] && $canViewEvidence)): ?><div class="flex flex-col gap-4 border-t border-border-warm bg-[#FFFCF4] p-5 sm:flex-row sm:items-end sm:justify-between"><?php if ($editable): ?><form action="<?= site_url('panel/opnames/' . $opname['id'] . '/evidence') ?>" method="post" enctype="multipart/form-data" class="grid flex-1 gap-3 sm:grid-cols-[1fr_auto] sm:items-end"><?= csrf_field() ?><div><label class="form-label" for="opname-evidence-<?= (int) $opname['id'] ?>"><?= $opname['evidence_path'] ? 'Ganti bukti hitung' : 'Bukti hitung' ?> <span class="font-normal text-ink-muted">(opsional)</span></label><input class="form-input file:mr-3 file:border-0 file:bg-brand-sun file:px-3 file:py-2 file:font-bold" id="opname-evidence-<?= (int) $opname['id'] ?>" name="evidence" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required><p class="mt-1 text-xs text-ink-muted">JPEG, PNG, WebP, atau PDF. Maksimal 5 MB.</p></div><button class="button-secondary" type="submit">Simpan bukti</button></form><?php endif ?><?php if ($opname['evidence_path'] && $canViewEvidence): ?><a class="shrink-0 font-bold underline decoration-brand-sun decoration-2 underline-offset-4" href="<?= site_url('panel/opnames/' . $opname['id'] . '/evidence') ?>">Unduh bukti privat</a><?php endif ?></div><?php endif ?>

                        <?php if ($editable): ?><form action="<?= site_url('panel/opnames/' . $opname['id'] . '/counts') ?>" method="post"><?php endif ?>
                        <?php if ($editable): ?><?= csrf_field() ?><?php endif ?>
                        <div class="overflow-x-auto border-t border-border-warm"><table class="w-full min-w-[48rem] text-sm"><thead class="bg-surface-soft-yellow text-left"><tr><th class="px-5 py-3">Item</th><th class="px-5 py-3 text-right">Stok sistem</th><th class="px-5 py-3 text-right">Fisik</th><th class="px-5 py-3 text-right">Selisih</th><th class="px-5 py-3">Catatan</th></tr></thead><tbody class="divide-y divide-border-warm">
                            <?php foreach ($lines as $line): ?><?php $difference = $line['difference_quantity'] !== null ? (float) $line['difference_quantity'] : null; ?>
                                <tr><td class="px-5 py-3"><strong class="block"><?= esc($line['item_name']) ?></strong><span class="text-xs text-ink-muted"><?= esc($line['sku']) ?> · <?= esc($line['base_unit']) ?></span></td><td class="px-5 py-3 text-right tabular-nums"><?= number_format((float) $line['system_quantity'], 3, ',', '.') ?></td><td class="px-5 py-3 text-right"><?php if ($editable): ?><input class="form-input ml-auto w-32 text-right" aria-label="Jumlah fisik <?= esc($line['item_name']) ?>" name="physical_quantities[<?= (int) $line['id'] ?>]" type="number" min="0" step="0.001" value="<?= $line['physical_quantity'] !== null ? esc($line['physical_quantity']) : '' ?>" required><?php else: ?><span class="tabular-nums"><?= $line['physical_quantity'] !== null ? number_format((float) $line['physical_quantity'], 3, ',', '.') : '-' ?></span><?php endif ?></td><td class="px-5 py-3 text-right font-bold tabular-nums <?= $difference !== null && $difference < 0 ? 'text-status-danger' : 'text-status-success' ?>"><?= $difference !== null ? (($difference > 0 ? '+' : '') . number_format($difference, 3, ',', '.')) : '-' ?></td><td class="px-5 py-3"><?php if ($editable): ?><input class="form-input min-w-52" aria-label="Catatan <?= esc($line['item_name']) ?>" name="item_notes[<?= (int) $line['id'] ?>]" value="<?= esc($line['notes']) ?>" maxlength="300"><?php else: ?><?= esc($line['notes'] ?: '-') ?><?php endif ?></td></tr>
                            <?php endforeach ?>
                        </tbody></table></div>
                        <?php if ($editable): ?><div class="border-t border-border-warm bg-[#FFFCF4] p-5"><button class="button-secondary" type="submit">Simpan hasil hitung</button></div></form><?php endif ?>

                        <?php if ($editable): ?><div class="border-t border-border-warm p-5"><form action="<?= site_url('panel/opnames/' . $opname['id'] . '/submit') ?>" method="post" data-confirm-form="Kirim hasil opname untuk persetujuan owner? Data tidak dapat diubah sebelum dikembalikan."><?= csrf_field() ?><button class="button-primary <?= $hasMissing ? 'cursor-not-allowed opacity-50' : '' ?>" type="submit" <?= $hasMissing ? 'disabled aria-disabled="true"' : '' ?>>Kirim untuk persetujuan</button><?php if ($hasMissing): ?><p class="mt-2 text-xs text-ink-muted">Simpan jumlah fisik seluruh item terlebih dahulu.</p><?php endif ?></form></div><?php endif ?>

                        <?php if (($user['role'] ?? '') === 'owner' && $opname['status'] === 'submitted'): ?>
                            <div class="grid gap-5 border-t border-brand-cocoa bg-[#FFFCF4] p-5 lg:grid-cols-2">
                                <form action="<?= site_url('panel/opnames/' . $opname['id'] . '/approve') ?>" method="post" data-confirm-form="Setujui dan posting selisih opname? Saldo stok akan berubah dan tindakan tidak dapat dibatalkan."><?= csrf_field() ?><h4 class="font-bold">Setujui penyesuaian</h4><p class="mt-2 text-sm text-ink-muted">Biaya hanya perlu diisi untuk kelebihan stok yang belum memiliki biaya rata-rata.</p><div class="mt-4 space-y-2"><?php foreach ($lines as $line): ?><?php if ((float) $line['difference_quantity'] > 0): ?><label class="grid grid-cols-[1fr_8rem] items-center gap-3 text-sm"><span><?= esc($line['item_name']) ?></span><input class="form-input text-right" aria-label="Biaya penyesuaian <?= esc($line['item_name']) ?>" name="adjustment_unit_costs[<?= (int) $line['id'] ?>]" type="number" min="0" step="0.0001" value="<?= esc($line['unit_cost_snapshot']) ?>"></label><?php endif ?><?php endforeach ?></div><button class="button-primary mt-4 w-full" type="submit">Setujui dan posting</button></form>
                                <form action="<?= site_url('panel/opnames/' . $opname['id'] . '/reject') ?>" method="post"><?= csrf_field() ?><h4 class="font-bold">Minta koreksi</h4><label class="form-label mt-4" for="review-<?= (int) $opname['id'] ?>">Catatan koreksi</label><textarea class="form-input min-h-28" id="review-<?= (int) $opname['id'] ?>" name="review_notes" minlength="5" maxlength="500" required></textarea><button class="button-secondary mt-4 w-full" type="submit">Kembalikan ke petugas</button></form>
                            </div>
                        <?php endif ?>

                        <?php if ($canCancel): ?><details class="border-t border-border-warm"><summary class="cursor-pointer px-5 py-4 text-sm font-bold text-status-danger focus-ring">Batalkan sesi</summary><form action="<?= site_url('panel/opnames/' . $opname['id'] . '/cancel') ?>" method="post" class="grid gap-3 border-t border-border-warm p-5 sm:grid-cols-[1fr_auto] sm:items-end" data-confirm-form="Batalkan sesi opname dan buka kunci stok?"><?= csrf_field() ?><div><label class="form-label" for="cancel-<?= (int) $opname['id'] ?>">Alasan pembatalan</label><input class="form-input" id="cancel-<?= (int) $opname['id'] ?>" name="cancellation_reason" minlength="5" maxlength="500" required></div><button class="button-secondary" type="submit">Batalkan sesi</button></form></details><?php endif ?>
                    </article>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</section>
<?= $this->endSection() ?>
