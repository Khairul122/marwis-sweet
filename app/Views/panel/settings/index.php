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

<?= panel_table('Semua data banner', ['Judul', 'Gambar', 'Urutan', 'Jadwal', 'Status', '>Aksi'], $bannerRows, ['createUrl' => 'panel/data/banner/create', 'width' => '52rem', 'empty' => 'Belum ada banner. Hero bawaan tetap tampil di katalog.']) ?>
<?= $this->endSection() ?>
