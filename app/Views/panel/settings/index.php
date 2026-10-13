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

        <section class="panel-card p-5 sm:p-7">
            <h2 class="text-xl font-bold">Tambah banner</h2>
            <p class="mt-1 text-sm text-ink-muted">Gunakan rasio 16:5. Banner tanpa jadwal akan langsung tersedia setelah diaktifkan.</p>
            <form action="<?= site_url('panel/settings/banners') ?>" method="post" enctype="multipart/form-data" class="mt-6 grid gap-5 sm:grid-cols-2">
                <?= csrf_field() ?>
                <div><label class="form-label" for="banner-title">Judul internal</label><input class="form-input" id="banner-title" name="title" required maxlength="140"></div>
                <div><label class="form-label" for="banner-alt">Teks alternatif</label><input class="form-input" id="banner-alt" name="alt_text" required minlength="5" maxlength="180" placeholder="Jelaskan isi gambar"></div>
                <div class="sm:col-span-2"><label class="form-label" for="banner-image">Gambar maksimal 3 MB</label><input class="form-input file:mr-3 file:border-0 file:bg-brand-sun file:px-3 file:py-1 file:font-bold" id="banner-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-image-input required></div>
                <div class="sm:col-span-2 hidden" data-image-preview-wrap>
                    <p class="form-label">Pratinjau desktop dan mobile</p>
                    <div class="grid gap-3"><img class="aspect-[16/5] w-full border border-border-warm object-cover" data-image-preview alt="Pratinjau banner desktop"><img class="aspect-[4/3] w-44 border border-border-warm object-cover" data-image-preview alt="Pratinjau banner mobile"></div>
                </div>
                <div><label class="form-label" for="banner-link">Tautan <span class="font-normal text-ink-muted">(opsional)</span></label><input class="form-input" id="banner-link" name="link_url" placeholder="/menu atau https://..."></div>
                <div><label class="form-label" for="banner-order">Urutan</label><input class="form-input" id="banner-order" name="sort_order" type="number" min="0" value="0"></div>
                <div><label class="form-label" for="starts-at">Mulai tampil <span class="font-normal text-ink-muted">(opsional)</span></label><input class="form-input" id="starts-at" name="starts_at" type="datetime-local"></div>
                <div><label class="form-label" for="ends-at">Selesai tampil <span class="font-normal text-ink-muted">(opsional)</span></label><input class="form-input" id="ends-at" name="ends_at" type="datetime-local"></div>
                <div class="sm:col-span-2"><button class="button-primary" type="submit">Unggah banner</button></div>
            </form>
        </section>
    </div>

    <aside class="panel-card overflow-hidden xl:sticky xl:top-24">
        <div class="border-b border-border-warm px-5 py-4"><h2 class="font-bold">Banner tersimpan</h2><p class="mt-1 text-sm text-ink-muted"><?= count($banners) ?> banner</p></div>
        <?php if ($banners === []): ?><div class="p-5"><p class="font-semibold">Belum ada banner.</p><p class="mt-1 text-sm text-ink-muted">Hero bawaan tetap tampil di katalog.</p></div><?php else: ?>
            <div class="divide-y divide-border-warm">
                <?php foreach ($banners as $banner): ?><article class="p-5"><img class="aspect-[16/5] w-full border border-border-warm object-cover" src="<?= base_url($banner['image_path']) ?>" alt="<?= esc($banner['alt_text']) ?>"><div class="mt-3 flex items-start justify-between gap-3"><div><h3 class="font-bold"><?= esc($banner['title']) ?></h3><p class="mt-1 text-xs text-ink-muted">Urutan <?= (int) $banner['sort_order'] ?><?= $banner['starts_at'] ? ' · mulai ' . esc(date('d/m/Y H:i', strtotime($banner['starts_at']))) : '' ?></p></div><form action="<?= site_url('panel/settings/banners/status') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="banner_id" value="<?= (int) $banner['id'] ?>"><button class="status-button <?= $banner['is_active'] ? 'is-active' : '' ?>" type="submit"><?= $banner['is_active'] ? 'Aktif' : 'Nonaktif' ?></button></form></div><details class="mt-3"><summary class="cursor-pointer text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-4 focus-ring">Edit banner</summary><form action="<?= site_url('panel/settings/banners/update') ?>" method="post" class="mt-4 space-y-3"><?= csrf_field() ?><input type="hidden" name="banner_id" value="<?= (int) $banner['id'] ?>"><div><label class="form-label" for="edit-banner-title-<?= (int) $banner['id'] ?>">Judul</label><input class="form-input" id="edit-banner-title-<?= (int) $banner['id'] ?>" name="title" value="<?= esc($banner['title']) ?>" required></div><div><label class="form-label" for="edit-banner-alt-<?= (int) $banner['id'] ?>">Teks alternatif</label><input class="form-input" id="edit-banner-alt-<?= (int) $banner['id'] ?>" name="alt_text" value="<?= esc($banner['alt_text']) ?>" required></div><div><label class="form-label" for="edit-banner-link-<?= (int) $banner['id'] ?>">Tautan</label><input class="form-input" id="edit-banner-link-<?= (int) $banner['id'] ?>" name="link_url" value="<?= esc($banner['link_url']) ?>"></div><div><label class="form-label" for="edit-banner-order-<?= (int) $banner['id'] ?>">Urutan</label><input class="form-input" id="edit-banner-order-<?= (int) $banner['id'] ?>" name="sort_order" type="number" min="0" value="<?= (int) $banner['sort_order'] ?>" required></div><div><label class="form-label" for="edit-banner-start-<?= (int) $banner['id'] ?>">Mulai</label><input class="form-input" id="edit-banner-start-<?= (int) $banner['id'] ?>" name="starts_at" type="datetime-local" value="<?= $banner['starts_at'] ? esc(date('Y-m-d\TH:i', strtotime($banner['starts_at']))) : '' ?>"></div><div><label class="form-label" for="edit-banner-end-<?= (int) $banner['id'] ?>">Selesai</label><input class="form-input" id="edit-banner-end-<?= (int) $banner['id'] ?>" name="ends_at" type="datetime-local" value="<?= $banner['ends_at'] ? esc(date('Y-m-d\TH:i', strtotime($banner['ends_at']))) : '' ?>"></div><button class="button-primary w-full" type="submit">Simpan banner</button></form></details></article><?php endforeach ?>
            </div>
        <?php endif ?>
    </aside>
</div>
<?= $this->endSection() ?>
