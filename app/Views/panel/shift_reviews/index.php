<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<section class="mx-auto max-w-6xl space-y-8">
    <header>
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-status-danger">Kontrol kas</p>
        <h1 class="mt-2 font-display text-3xl">Review selisih shift</h1>
        <p class="mt-2 max-w-3xl leading-7 text-ink-muted">Periksa kas fisik dan catatan kasir. Persetujuan akan membukukan penyesuaian ke akun kas laci dan beban selisih kas.</p>
    </header>

    <section class="space-y-4" aria-labelledby="pending-title">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 id="pending-title" class="text-xl font-bold">Menunggu keputusan</h2><p class="mt-1 text-sm text-ink-muted"><?= count($shifts) ?> shift perlu ditinjau</p></div>
        </div>
        <?php if ($shifts === []): ?>
            <div class="panel-card p-6 text-ink-muted">Tidak ada selisih shift yang menunggu review.</div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($shifts as $shift): ?>
                    <?php $difference = (float) $shift['cash_difference']; ?>
                    <article class="panel-card overflow-hidden">
                        <div class="grid gap-5 border-b border-border-warm p-5 md:grid-cols-[1fr_auto] md:items-start">
                            <div>
                                <div class="flex flex-wrap items-center gap-2"><h3 class="font-bold"><?= esc($shift['shift_number']) ?></h3><span class="status-label bg-[#FEF2F2] text-status-danger">Perlu review</span></div>
                                <p class="mt-1 text-sm text-ink-muted">Kasir <?= esc($shift['cashier_name']) ?> · ditutup <?= esc(date('d/m/Y H:i', strtotime($shift['closed_at']))) ?></p>
                                <p class="mt-3 text-sm leading-6"><strong>Alasan kasir:</strong> <?= esc($shift['difference_reason'] ?: 'Tidak ada alasan yang dicatat.') ?></p>
                            </div>
                            <div class="rounded-lg bg-surface-soft-yellow px-4 py-3 text-left md:min-w-52 md:text-right">
                                <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Selisih kas</p>
                                <p class="mt-1 text-xl font-bold tabular-nums <?= $difference < 0 ? 'text-status-danger' : 'text-status-success' ?>">Rp<?= number_format($difference, 2, ',', '.') ?></p>
                                <p class="mt-2 text-xs text-ink-muted">Seharusnya Rp<?= number_format((float) $shift['expected_cash'], 2, ',', '.') ?> · fisik Rp<?= number_format((float) $shift['actual_cash'], 2, ',', '.') ?></p>
                            </div>
                        </div>
                        <div class="grid gap-5 p-5 lg:grid-cols-2">
                            <form action="<?= site_url('panel/shift-reviews/' . $shift['id'] . '/approve') ?>" method="post" class="space-y-3" data-confirm-form="Setujui dan posting penyesuaian selisih kas ini?">
                                <?= csrf_field() ?>
                                <label class="form-label" for="approve-reason-<?= (int) $shift['id'] ?>">Catatan persetujuan</label>
                                <textarea class="form-input min-h-20" id="approve-reason-<?= (int) $shift['id'] ?>" name="reason" minlength="3" maxlength="500" required placeholder="Contoh: bukti hitung ulang sudah cocok"></textarea>
                                <button class="button-primary w-full" type="submit">Setujui dan posting</button>
                            </form>
                            <form action="<?= site_url('panel/shift-reviews/' . $shift['id'] . '/reject') ?>" method="post" class="space-y-3" data-confirm-form="Tolak selisih ini? Jurnal penyesuaian tidak akan dibuat.">
                                <?= csrf_field() ?>
                                <label class="form-label" for="reject-reason-<?= (int) $shift['id'] ?>">Alasan penolakan</label>
                                <textarea class="form-input min-h-20" id="reject-reason-<?= (int) $shift['id'] ?>" name="reason" minlength="3" maxlength="500" required placeholder="Jelaskan tindak lanjut atau alasan penolakan"></textarea>
                                <button class="button-secondary w-full" type="submit">Tolak selisih</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>

    <?php if ($recentReviews !== []): ?>
        <section class="space-y-4" aria-labelledby="history-title">
            <div><h2 id="history-title" class="text-xl font-bold">Keputusan terakhir</h2><p class="mt-1 text-sm text-ink-muted">Riwayat keputusan owner untuk audit.</p></div>
            <div class="panel-card divide-y divide-border-warm">
                <?php foreach ($recentReviews as $review): ?>
                    <article class="grid gap-2 p-5 md:grid-cols-[1fr_auto] md:items-start">
                        <div><div class="flex flex-wrap items-center gap-2"><strong><?= esc($review['shift_number']) ?></strong><span class="status-label <?= $review['cash_review_status'] === 'approved' ? 'bg-[#F0FDF4] text-status-success' : 'bg-[#FEF2F2] text-status-danger' ?>"><?= $review['cash_review_status'] === 'approved' ? 'Disetujui' : 'Ditolak' ?></span></div><p class="mt-1 text-sm text-ink-muted">Kasir <?= esc($review['cashier_name']) ?> · ditinjau <?= esc(date('d/m/Y H:i', strtotime($review['cash_reviewed_at']))) ?> oleh <?= esc($review['reviewer_name'] ?? 'Owner') ?></p><p class="mt-2 text-sm"><strong>Catatan:</strong> <?= esc($review['cash_review_reason']) ?></p></div>
                        <p class="font-bold tabular-nums <?= (float) $review['cash_difference'] < 0 ? 'text-status-danger' : 'text-status-success' ?>">Rp<?= number_format((float) $review['cash_difference'], 2, ',', '.') ?></p>
                    </article>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
