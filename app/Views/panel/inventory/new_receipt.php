<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<nav class="mb-6 text-sm" aria-label="Breadcrumb"><a class="font-bold underline decoration-brand-sun decoration-2 underline-offset-4 focus-ring" href="<?= site_url('panel/inventory') ?>">Kembali ke stok</a></nav>
<div class="mb-6 max-w-3xl">
    <p class="section-kicker">Penerimaan stok</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Buat draft penerimaan</h1>
    <p class="mt-3 text-ink-muted">Isi informasi dokumen terlebih dahulu. Stok dan jurnal belum berubah sampai owner menyetujui biaya dan penerimaan diposting.</p>
</div>
<section class="panel-card max-w-3xl p-5 sm:p-7">
    <form action="<?= site_url('panel/inventory/receipts') ?>" method="post" class="grid gap-5 sm:grid-cols-2">
        <?= csrf_field() ?>
        <div><label class="form-label" for="reference-number">Nomor referensi</label><input class="form-input" id="reference-number" name="reference_number" value="<?= esc(old('reference_number')) ?>" maxlength="80" required autocomplete="off" placeholder="Contoh: PB-2026-001"></div>
        <div><label class="form-label" for="document-date">Tanggal dokumen</label><input class="form-input" id="document-date" name="document_date" type="date" value="<?= esc(old('document_date') ?: date('Y-m-d')) ?>" required></div>
        <div><label class="form-label" for="supplier-id">Supplier <span class="font-normal text-ink-muted">(opsional)</span></label><select class="form-input" id="supplier-id" name="supplier_id"><option value="">Tanpa supplier</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int) $supplier['id'] ?>" <?= (string) old('supplier_id') === (string) $supplier['id'] ? 'selected' : '' ?>><?= esc($supplier['name']) ?></option><?php endforeach ?></select></div>
        <div><label class="form-label" for="payment-source">Sumber pembayaran</label><select class="form-input" id="payment-source" name="payment_source" required><option value="cash" <?= old('payment_source', 'cash') === 'cash' ? 'selected' : '' ?>>Kas usaha</option><option value="bank" <?= old('payment_source') === 'bank' ? 'selected' : '' ?>>Bank</option><option value="payable" <?= old('payment_source') === 'payable' ? 'selected' : '' ?>>Utang supplier</option></select></div>
        <div class="sm:col-span-2"><label class="form-label" for="receipt-notes">Catatan <span class="font-normal text-ink-muted">(opsional)</span></label><textarea class="form-input min-h-24" id="receipt-notes" name="notes" maxlength="500"><?= esc(old('notes')) ?></textarea></div>
        <div class="flex flex-col-reverse gap-3 border-t border-border-warm pt-5 sm:col-span-2 sm:flex-row sm:justify-end">
            <a class="button-secondary justify-center" href="<?= site_url('panel/inventory') ?>">Batal</a>
            <button class="button-primary justify-center" type="submit">Simpan draft</button>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
