<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<div class="mb-8">
    <p class="section-kicker">Tampilan publik</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Identitas usaha dan banner</h1>
    <p class="mt-3 max-w-3xl text-ink-muted">Informasi yang disimpan di sini dipakai pada katalog publik. Isi hanya data yang siap dilihat pelanggan.</p>
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem] xl:items-start">
    <div class="space-y-6">
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

    <aside class="panel-card overflow-hidden xl:sticky xl:top-24">
        <div class="flex items-start justify-between gap-3 border-b border-border-warm px-5 py-4"><div><h2 class="font-bold">Banner tersimpan</h2><p class="mt-1 text-sm text-ink-muted"><?= count($banners) ?> banner</p></div><a class="button-primary !min-h-9 !px-3" href="<?= site_url('panel/form/banner') ?>">Tambah banner</a></div>
        <?php if ($banners === []): ?><div class="p-5"><p class="font-semibold">Belum ada banner.</p><p class="mt-1 text-sm text-ink-muted">Hero bawaan tetap tampil di katalog.</p></div><?php else: ?>
            <div class="divide-y divide-border-warm">
                <?php foreach ($banners as $banner): ?><article class="p-5"><img class="aspect-[16/5] w-full border border-border-warm object-cover" src="<?= base_url($banner['image_path']) ?>" alt="<?= esc($banner['alt_text']) ?>"><div class="mt-3 flex items-start justify-between gap-3"><div><h3 class="font-bold"><?= esc($banner['title']) ?></h3><p class="mt-1 text-xs text-ink-muted">Urutan <?= (int) $banner['sort_order'] ?><?= $banner['starts_at'] ? ' · mulai ' . esc(date('d/m/Y H:i', strtotime($banner['starts_at']))) : '' ?></p></div><form action="<?= site_url('panel/settings/banners/status') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="banner_id" value="<?= (int) $banner['id'] ?>"><button class="status-button <?= $banner['is_active'] ? 'is-active' : '' ?>" type="submit"><?= $banner['is_active'] ? 'Aktif' : 'Nonaktif' ?></button></form></div><a class="inline-flex min-h-11 items-center text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-4 " href="<?= site_url('panel/form/banner/' . (int) $banner['id']) ?>">Edit banner</a></article><?php endforeach ?>
            </div>
        <?php endif ?>
    </aside>
</div>
<?= $this->endSection() ?>
