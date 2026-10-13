<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<div class="mb-8 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
    <div>
        <p class="section-kicker">Master usaha</p>
        <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Produk dan kategori</h1>
        <p class="mt-3 max-w-2xl text-ink-muted">Susun kategori, produk, varian, harga, dan status publik dari satu halaman.</p>
    </div>
    <div class="flex flex-wrap gap-3 self-start"><a class="button-primary" href="<?= site_url('panel/form/product') ?>">Tambah produk</a><a class="button-secondary" href="<?= site_url('/') ?>" target="_blank" rel="noopener">Buka katalog publik</a></div>
</div>

<div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)] xl:items-start">
    <aside class="space-y-6 xl:sticky xl:top-24">
        <section class="panel-card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-border-warm px-5 py-4"><h2 class="font-bold">Kategori tersedia</h2><a class="button-primary !min-h-9 !px-3" href="<?= site_url('panel/form/category') ?>">Tambah</a></div>
            <?php if ($categories === []): ?>
                <div class="p-5"><p class="font-semibold">Belum ada kategori.</p><p class="mt-1 text-sm text-ink-muted">Tambahkan kategori sebelum membuat produk.</p></div>
            <?php else: ?>
                <div class="divide-y divide-border-warm">
                    <?php foreach ($categories as $category): ?>
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between gap-3"><div class="min-w-0"><p class="truncate font-semibold"><?= esc($category['name']) ?></p><p class="mt-1 text-xs text-ink-muted">Urutan <?= (int) $category['sort_order'] ?></p></div><form action="<?= site_url('panel/catalog/categories/status') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>"><button class="status-button <?= $category['is_active'] ? 'is-active' : '' ?>" type="submit" aria-label="<?= $category['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> kategori <?= esc($category['name']) ?>"><?= $category['is_active'] ? 'Aktif' : 'Nonaktif' ?></button></form></div>
                            <a class="mt-3 inline-flex min-h-11 items-center text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-4" href="<?= site_url('panel/form/category/' . (int) $category['id']) ?>">Edit kategori</a>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </section>
    </aside>

    <div class="space-y-6">
        <section>
            <div class="mb-4 flex items-end justify-between gap-4"><div><h2 class="text-xl font-bold">Daftar produk</h2><p class="mt-1 text-sm text-ink-muted"><?= count($products) ?> produk tersimpan</p></div></div>
            <?php if ($products === []): ?>
                <div class="panel-card p-8"><p class="font-display text-2xl">Belum ada produk.</p><p class="mt-2 text-ink-muted">Pilih "Tambah produk" untuk menerbitkan menu pertama.</p></div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($products as $product): ?>
                        <?php $variants = $variantsByProduct[$product['id']] ?? []; ?>
                        <?php $images = $imagesByProduct[$product['id']] ?? []; ?>
                        <article class="panel-card overflow-hidden">
                            <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2"><span class="text-xs font-bold uppercase tracking-wider text-brand-chili"><?= esc($product['category_name']) ?></span><?php if ($product['is_featured']): ?><span class="status-label bg-brand-sun text-brand-cocoa">Unggulan</span><?php endif ?></div>
                                    <h3 class="mt-2 font-display text-2xl"><?= esc($product['name']) ?></h3>
                                    <?php if ($product['description']): ?><p class="mt-2 max-w-2xl text-sm leading-6 text-ink-muted"><?= esc($product['description']) ?></p><?php endif ?>
                                    <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                        <?php foreach ($variants as $variant): ?><span><strong><?= esc($variant['name']) ?></strong> <span class="tabular-nums text-ink-muted">Rp<?= number_format((float) $variant['price'], 0, ',', '.') ?></span></span><?php endforeach ?>
                                    </div>
                                </div>
                                <form action="<?= site_url('panel/catalog/products/status') ?>" method="post">
                                    <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <button class="status-button <?= $product['is_active'] ? 'is-active' : '' ?>" type="submit"><?= $product['is_active'] ? 'Tampil di katalog' : 'Disembunyikan' ?></button>
                                </form>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 border-t border-border-warm bg-[#FFFCF4] px-5 py-4 sm:px-6">
                                <a class="button-secondary" href="<?= site_url('panel/form/product/' . (int) $product['id']) ?>">Edit produk</a>
                                <a class="button-secondary" href="<?= site_url('panel/form/variant?product_id=' . (int) $product['id']) ?>">Tambah varian</a>
                                <?php foreach ($variants as $variant): ?><a class="text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-4" href="<?= site_url('panel/form/variant/' . (int) $variant['id']) ?>">Edit varian <?= esc($variant['name']) ?></a><?php endforeach ?>
                            </div>
                            <details class="border-t border-border-warm bg-[#FFFCF4]">
                                <summary class="cursor-pointer px-5 py-4 text-sm font-bold focus-ring sm:px-6">Kelola foto produk (<?= count($images) ?>/5)</summary>
                                <div class="border-t border-border-warm p-5 sm:p-6">
                                    <?php if ($images !== []): ?>
                                        <div class="mb-5 flex gap-3 overflow-x-auto pb-2">
                                            <?php foreach ($images as $image): ?><img class="h-24 w-32 shrink-0 border border-border-warm object-cover" src="<?= base_url($image['image_path']) ?>" alt="<?= esc($image['alt_text']) ?>"><?php endforeach ?>
                                        </div>
                                    <?php endif ?>
                                    <?php if (count($images) < 5): ?>
                                        <form action="<?= site_url('panel/catalog/products/images') ?>" method="post" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                                            <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                            <div><label class="form-label" for="photo-<?= (int) $product['id'] ?>">Foto JPEG, PNG, atau WebP</label><input class="form-input file:mr-3 file:border-0 file:bg-brand-sun file:px-3 file:py-1 file:font-bold" id="photo-<?= (int) $product['id'] ?>" name="image" type="file" accept="image/jpeg,image/png,image/webp" required></div>
                                            <div><label class="form-label" for="alt-<?= (int) $product['id'] ?>">Deskripsi foto</label><input class="form-input" id="alt-<?= (int) $product['id'] ?>" name="alt_text" placeholder="Contoh: Martabak cokelat keju" minlength="5" maxlength="180" required></div>
                                            <button class="button-primary" type="submit">Unggah foto</button>
                                        </form>
                                    <?php endif ?>
                                </div>
                            </details>
                        </article>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
