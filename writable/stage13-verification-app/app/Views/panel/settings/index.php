<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<div class="mb-8">
    <p class="section-kicker">Tampilan publik</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Identitas usaha dan banner</h1>
    <p class="mt-3 max-w-3xl text-ink-muted">Informasi yang disimpan di sini dipakai pada katalog publik. Isi hanya data yang siap dilihat pelanggan.</p>
</div>

<?php
$bannerRows = array_map(static fn (array $b): array => [
    '<strong>' . esc($b['title']) . '</strong>',
    '<img class="h-10 w-24 border border-border-warm object-cover" src="' . base_url($b['image_path']) . '" alt="' . esc($b['alt_text']) . '" loading="lazy">',
    (string) (int) $b['sort_order'],
    $b['starts_at'] || $b['ends_at'] ? esc(($b['starts_at'] ? date('d/m/Y H:i', strtotime($b['starts_at'])) : 'Sekarang') . ' - ' . ($b['ends_at'] ? date('d/m/Y H:i', strtotime($b['ends_at'])) : 'Tanpa batas')) : 'Selalu tampil',
    panel_badge((bool) $b['is_active']),
    panel_actions([
        'edit' => 'panel/data/banner/' . $b['id'] . '/edit',
        'toggle' => ['url' => 'panel/settings/banners/status', 'field' => 'banner_id', 'id' => $b['id'], 'active' => (bool) $b['is_active'], 'label' => 'banner'],
        'delete' => ['url' => 'panel/settings/banners/delete', 'field' => 'banner_id', 'id' => $b['id'], 'name' => $b['title'], 'label' => 'banner'],
    ]),
], $banners);
?>
<div class="mb-8 max-w-3xl">
        <section class="panel-card p-5 sm:p-7">
            <h2 class="text-xl font-bold">Informasi usaha</h2>
            <form action="<?= site_url('panel/settings/business') ?>" method="post" class="mt-6 grid gap-5 sm:grid-cols-2">
                <?= csrf_field() ?>
                <div class="sm:col-span-2"><label class="form-label" for="business-name">Nama usaha</label><input class="form-input" id="business-name" name="business_name" value="<?= esc(old('business_name', $settings['business_name'] ?? env('BUSINESS_NAME', 'Marwis Sweet'))) ?>" required maxlength="120"></div>
                <div><label class="form-label" for="business-phone">Nomor kontak <span class="font-normal text-ink-muted">(opsional)</span></label><input class="form-input" id="business-phone" name="phone" inputmode="tel" value="<?= esc(old('phone', $settings['phone'] ?? '')) ?>"></div>
                <div><label class="form-label" for="opening-hours">Jam buka <span class="font-normal text-ink-muted">(opsional)</span></label><input class="form-input" id="opening-hours" name="opening_hours" value="<?= esc(old('opening_hours', $settings['opening_hours'] ?? '')) ?>" placeholder="Contoh: Senin sampai Minggu, 16.00 sampai 23.00"></div>
                <div class="sm:col-span-2"><label class="form-label" for="business-address">Alamat <span class="font-normal text-ink-muted">(opsional)</span></label><textarea class="form-input min-h-24" id="business-address" name="address" maxlength="500"><?= esc(old('address', $settings['address'] ?? '')) ?></textarea></div>
                <div class="sm:col-span-2"><button class="button-primary" type="submit">Simpan informasi usaha</button></div>
            </form>
        </section>
</div>

<section class="panel-card mb-8 max-w-4xl p-5 sm:p-7">
    <div>
        <h2 class="text-xl font-bold">Aturan pembayaran dan harga</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-ink-muted">Batas diskon mulai dari Rp0. Diskon yang melewati batas perlu persetujuan owner di kasir. Pajak dan biaya layanan tidak dihitung sampai diaktifkan.</p>
    </div>
    <form action="<?= site_url('panel/settings/sales-policy') ?>" method="post" class="mt-6 grid gap-5 sm:grid-cols-2">
        <?= csrf_field() ?>
        <div><label class="form-label" for="item-discount-max-amount">Batas diskon item (nominal)</label><div class="input-prefix"><span>Rp</span><input class="form-input" id="item-discount-max-amount" name="item_discount_max_amount" type="number" min="0" step="0.01" value="<?= esc(old('item_discount_max_amount', $policy['item_discount_max_amount'])) ?>" required></div></div>
        <div><label class="form-label" for="item-discount-max-percent">Batas diskon item (persen)</label><div class="flex items-center gap-2"><input class="form-input" id="item-discount-max-percent" name="item_discount_max_percent" type="number" min="0" max="100" step="0.01" value="<?= esc(old('item_discount_max_percent', $policy['item_discount_max_percent'])) ?>" required><span aria-hidden="true">%</span></div></div>
        <div><label class="form-label" for="transaction-discount-max-amount">Batas diskon transaksi (nominal)</label><div class="input-prefix"><span>Rp</span><input class="form-input" id="transaction-discount-max-amount" name="transaction_discount_max_amount" type="number" min="0" step="0.01" value="<?= esc(old('transaction_discount_max_amount', $policy['transaction_discount_max_amount'])) ?>" required></div></div>
        <div><label class="form-label" for="transaction-discount-max-percent">Batas diskon transaksi (persen)</label><div class="flex items-center gap-2"><input class="form-input" id="transaction-discount-max-percent" name="transaction_discount_max_percent" type="number" min="0" max="100" step="0.01" value="<?= esc(old('transaction_discount_max_percent', $policy['transaction_discount_max_percent'])) ?>" required><span aria-hidden="true">%</span></div></div>
        <div class="border-t border-border-warm pt-5 sm:col-span-2"><h3 class="font-bold">Biaya tambahan</h3><p class="mt-1 text-sm leading-6 text-ink-muted">Biaya layanan dihitung setelah diskon. Pajak dihitung dari subtotal setelah diskon ditambah biaya layanan.</p></div>
        <div><label class="form-label" for="service-charge-enabled">Biaya layanan</label><select class="form-input" id="service-charge-enabled" name="service_charge_enabled"><option value="0" <?= old('service_charge_enabled', $policy['service_charge_enabled'] ? '1' : '0') === '0' ? 'selected' : '' ?>>Nonaktif</option><option value="1" <?= old('service_charge_enabled', $policy['service_charge_enabled'] ? '1' : '0') === '1' ? 'selected' : '' ?>>Aktif</option></select></div>
        <div><label class="form-label" for="service-charge-rate">Tarif biaya layanan</label><div class="flex items-center gap-2"><input class="form-input" id="service-charge-rate" name="service_charge_rate" type="number" min="0" max="100" step="0.01" value="<?= esc(old('service_charge_rate', $policy['service_charge_rate'])) ?>" required><span aria-hidden="true">%</span></div></div>
        <div><label class="form-label" for="tax-enabled">Pajak</label><select class="form-input" id="tax-enabled" name="tax_enabled"><option value="0" <?= old('tax_enabled', $policy['tax_enabled'] ? '1' : '0') === '0' ? 'selected' : '' ?>>Nonaktif</option><option value="1" <?= old('tax_enabled', $policy['tax_enabled'] ? '1' : '0') === '1' ? 'selected' : '' ?>>Aktif</option></select></div>
        <div><label class="form-label" for="tax-rate">Tarif pajak</label><div class="flex items-center gap-2"><input class="form-input" id="tax-rate" name="tax_rate" type="number" min="0" max="100" step="0.01" value="<?= esc(old('tax_rate', $policy['tax_rate'])) ?>" required><span aria-hidden="true">%</span></div></div>
        <div class="sm:col-span-2"><button class="button-primary" type="submit">Simpan aturan harga</button></div>
    </form>
</section>

<?= panel_table('Semua data banner', ['Judul', 'Gambar', 'Urutan', 'Jadwal', 'Status', '>Aksi'], $bannerRows, ['createUrl' => 'panel/data/banner/create', 'width' => '52rem', 'empty' => 'Belum ada banner. Hero bawaan tetap tampil di katalog.']) ?>
<?= $this->endSection() ?>
