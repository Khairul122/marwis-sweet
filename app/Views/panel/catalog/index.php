<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<div class="mb-8 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
    <div>
        <p class="section-kicker">Master usaha</p>
        <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Produk dan kategori</h1>
        <p class="mt-3 max-w-2xl text-ink-muted">Susun kategori, produk, varian, harga, dan status publik dari satu halaman.</p>
    </div>
    <a class="button-secondary self-start" href="<?= site_url('/') ?>" target="_blank" rel="noopener">Buka katalog publik</a>
</div>

<?php $categoryErrors = session()->getFlashdata('categoryErrors') ?? []; ?>
<?php $productErrors = session()->getFlashdata('productErrors') ?? []; ?>

<div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)] xl:items-start">
    <aside class="space-y-6 xl:sticky xl:top-24">
        <section class="panel-card p-5 sm:p-6">
            <h2 class="text-lg font-bold">Tambah kategori</h2>
            <p class="mt-1 text-sm text-ink-muted">Kategori aktif menjadi filter di katalog publik.</p>
            <form action="<?= site_url('panel/catalog/categories') ?>" method="post" class="mt-5 space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="form-label" for="category-name">Nama kategori</label>
                    <input class="form-input" id="category-name" name="name" value="<?= esc(old('name')) ?>" maxlength="100" required aria-describedby="category-name-error">
                    <?php if (isset($categoryErrors['name'])): ?><p class="form-error" id="category-name-error"><?= esc($categoryErrors['name']) ?></p><?php endif ?>
                </div>
                <div>
                    <label class="form-label" for="category-description">Keterangan <span class="font-normal text-ink-muted">(opsional)</span></label>
                    <textarea class="form-input min-h-24 resize-y" id="category-description" name="description" maxlength="255"><?= esc(old('description')) ?></textarea>
                    <?php if (isset($categoryErrors['description'])): ?><p class="form-error"><?= esc($categoryErrors['description']) ?></p><?php endif ?>
                </div>
                <div>
                    <label class="form-label" for="sort-order">Urutan</label>
                    <input class="form-input" id="sort-order" name="sort_order" type="number" min="0" max="99999" value="<?= esc(old('sort_order', '0')) ?>">
                </div>
                <button class="button-primary w-full" type="submit">Tambahkan kategori</button>
            </form>
        </section>

        <section class="panel-card overflow-hidden">
            <div class="border-b border-border-warm px-5 py-4"><h2 class="font-bold">Kategori tersedia</h2></div>
            <?php if ($categories === []): ?>
                <div class="p-5"><p class="font-semibold">Belum ada kategori.</p><p class="mt-1 text-sm text-ink-muted">Tambahkan kategori sebelum membuat produk.</p></div>
            <?php else: ?>
                <div class="divide-y divide-border-warm">
                    <?php foreach ($categories as $category): ?>
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between gap-3"><div class="min-w-0"><p class="truncate font-semibold"><?= esc($category['name']) ?></p><p class="mt-1 text-xs text-ink-muted">Urutan <?= (int) $category['sort_order'] ?></p></div><form action="<?= site_url('panel/catalog/categories/status') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>"><button class="status-button <?= $category['is_active'] ? 'is-active' : '' ?>" type="submit" aria-label="<?= $category['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> kategori <?= esc($category['name']) ?>"><?= $category['is_active'] ? 'Aktif' : 'Nonaktif' ?></button></form></div>
                            <details class="mt-3"><summary class="cursor-pointer text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-4 focus-ring">Edit kategori</summary><form action="<?= site_url('panel/catalog/categories/update') ?>" method="post" class="mt-4 space-y-3"><?= csrf_field() ?><input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>"><div><label class="form-label" for="edit-category-<?= (int) $category['id'] ?>">Nama</label><input class="form-input" id="edit-category-<?= (int) $category['id'] ?>" name="name" value="<?= esc($category['name']) ?>" required></div><div><label class="form-label" for="edit-category-description-<?= (int) $category['id'] ?>">Keterangan</label><textarea class="form-input min-h-20" id="edit-category-description-<?= (int) $category['id'] ?>" name="description"><?= esc($category['description']) ?></textarea></div><div><label class="form-label" for="edit-category-order-<?= (int) $category['id'] ?>">Urutan</label><input class="form-input" id="edit-category-order-<?= (int) $category['id'] ?>" name="sort_order" type="number" min="0" value="<?= (int) $category['sort_order'] ?>" required></div><button class="button-primary w-full" type="submit">Simpan kategori</button></form></details>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </section>
    </aside>

    <div class="space-y-6">
        <section class="panel-card p-5 sm:p-7">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div><h2 class="text-xl font-bold">Tambah produk</h2><p class="mt-1 text-sm text-ink-muted">Satu varian awal dibuat bersama produk.</p></div>
                <span class="label-cut bg-brand-sun px-3 py-2 text-xs font-bold">Tampil setelah disimpan</span>
            </div>
            <?php if ($categories === []): ?>
                <div class="mt-5 border border-status-warning bg-[#FFF7ED] p-4 text-sm text-[#78350F]" role="status">Buat minimal satu kategori sebelum menambahkan produk.</div>
            <?php else: ?>
                <form action="<?= site_url('panel/catalog/products') ?>" method="post" class="mt-6 grid gap-5 md:grid-cols-2">
                    <?= csrf_field() ?>
                    <div>
                        <label class="form-label" for="product-name">Nama produk</label>
                        <input class="form-input" id="product-name" name="name" value="<?= esc(old('name')) ?>" maxlength="140" required>
                        <?php if (isset($productErrors['name'])): ?><p class="form-error"><?= esc($productErrors['name']) ?></p><?php endif ?>
                    </div>
                    <div>
                        <label class="form-label" for="category-id">Kategori</label>
                        <select class="form-input" id="category-id" name="category_id" required>
                            <option value="">Pilih kategori</option>
                            <?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (string) old('category_id') === (string) $category['id'] ? 'selected' : '' ?>><?= esc($category['name']) ?></option><?php endforeach ?>
                        </select>
                        <?php if (isset($productErrors['category_id'])): ?><p class="form-error"><?= esc($productErrors['category_id']) ?></p><?php endif ?>
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label" for="product-description">Deskripsi singkat <span class="font-normal text-ink-muted">(opsional)</span></label>
                        <textarea class="form-input min-h-24 resize-y" id="product-description" name="description" maxlength="1000"><?= esc(old('description')) ?></textarea>
                    </div>
                    <div>
                        <label class="form-label" for="product-type">Jenis produk</label>
                        <select class="form-input" id="product-type" name="product_type" required><option value="prepared">Dibuat dari resep</option><option value="stocked">Barang siap jual</option></select>
                    </div>
                    <div>
                        <label class="form-label" for="variant-name">Nama varian awal</label>
                        <input class="form-input" id="variant-name" name="variant_name" value="<?= esc(old('variant_name', 'Reguler')) ?>" maxlength="100" required>
                    </div>
                    <div>
                        <label class="form-label" for="sku">SKU unik</label>
                        <input class="form-input uppercase" id="sku" name="sku" value="<?= esc(old('sku')) ?>" maxlength="80" required>
                        <?php if (isset($productErrors['sku'])): ?><p class="form-error"><?= esc($productErrors['sku']) ?></p><?php endif ?>
                    </div>
                    <div>
                        <label class="form-label" for="price">Harga jual</label>
                        <div class="input-prefix"><span>Rp</span><input id="price" name="price" type="number" min="0" step="1" value="<?= esc(old('price')) ?>" required></div>
                        <?php if (isset($productErrors['price'])): ?><p class="form-error"><?= esc($productErrors['price']) ?></p><?php endif ?>
                    </div>
                    <div>
                        <label class="form-label" for="stock-mode">Pengurangan stok</label>
                        <select class="form-input" id="stock-mode" name="stock_mode" required><option value="recipe">Berdasarkan resep</option><option value="direct">Stok produk langsung</option><option value="none">Tanpa pelacakan stok</option></select>
                    </div>
                    <label class="flex min-h-12 items-center gap-3 border border-border-warm bg-surface-soft-yellow px-4 text-sm font-semibold"><input class="h-5 w-5 accent-brand-cocoa" type="checkbox" name="is_featured" value="1"> Jadikan menu unggulan</label>
                    <div class="md:col-span-2"><button class="button-primary" type="submit">Simpan produk</button></div>
                </form>
            <?php endif ?>
        </section>

        <section>
            <div class="mb-4 flex items-end justify-between gap-4"><div><h2 class="text-xl font-bold">Daftar produk</h2><p class="mt-1 text-sm text-ink-muted"><?= count($products) ?> produk tersimpan</p></div></div>
            <?php if ($products === []): ?>
                <div class="panel-card p-8"><p class="font-display text-2xl">Belum ada produk.</p><p class="mt-2 text-ink-muted">Gunakan formulir di atas untuk menerbitkan menu pertama.</p></div>
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
                            <details class="border-t border-border-warm bg-[#FFFCF4]">
                                <summary class="cursor-pointer px-5 py-4 text-sm font-bold focus-ring sm:px-6">Edit produk dan varian</summary>
                                <div class="space-y-6 border-t border-border-warm p-5 sm:p-6">
                                    <form action="<?= site_url('panel/catalog/products/update') ?>" method="post" class="grid gap-4 sm:grid-cols-2">
                                        <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                        <div><label class="form-label" for="edit-product-name-<?= (int) $product['id'] ?>">Nama produk</label><input class="form-input" id="edit-product-name-<?= (int) $product['id'] ?>" name="name" value="<?= esc($product['name']) ?>" required></div>
                                        <div><label class="form-label" for="edit-product-category-<?= (int) $product['id'] ?>">Kategori</label><select class="form-input" id="edit-product-category-<?= (int) $product['id'] ?>" name="category_id" required><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) $category['id'] === (int) $product['category_id'] ? 'selected' : '' ?>><?= esc($category['name']) ?></option><?php endforeach ?></select></div>
                                        <div class="sm:col-span-2"><label class="form-label" for="edit-product-description-<?= (int) $product['id'] ?>">Deskripsi</label><textarea class="form-input min-h-24" id="edit-product-description-<?= (int) $product['id'] ?>" name="description"><?= esc($product['description']) ?></textarea></div>
                                        <div><label class="form-label" for="edit-product-type-<?= (int) $product['id'] ?>">Jenis produk</label><select class="form-input" id="edit-product-type-<?= (int) $product['id'] ?>" name="product_type"><option value="prepared" <?= $product['product_type'] === 'prepared' ? 'selected' : '' ?>>Dibuat dari resep</option><option value="stocked" <?= $product['product_type'] === 'stocked' ? 'selected' : '' ?>>Barang siap jual</option></select></div>
                                        <label class="flex min-h-12 items-center gap-3 border border-border-warm bg-white px-4 text-sm font-semibold"><input class="h-5 w-5 accent-brand-cocoa" type="checkbox" name="is_featured" value="1" <?= $product['is_featured'] ? 'checked' : '' ?>> Menu unggulan</label>
                                        <div class="sm:col-span-2"><button class="button-primary" type="submit">Simpan produk</button></div>
                                    </form>
                                    <?php if ($variants !== []): ?><div class="border-t border-border-warm pt-5"><h4 class="font-bold">Varian tersimpan</h4><div class="mt-4 space-y-4"><?php foreach ($variants as $variant): ?><form action="<?= site_url('panel/catalog/variants/update') ?>" method="post" class="grid gap-3 border border-border-warm bg-white p-4 sm:grid-cols-2 lg:grid-cols-4"><?= csrf_field() ?><input type="hidden" name="variant_id" value="<?= (int) $variant['id'] ?>"><div><label class="form-label" for="edit-variant-name-<?= (int) $variant['id'] ?>">Nama</label><input class="form-input" id="edit-variant-name-<?= (int) $variant['id'] ?>" name="variant_name" value="<?= esc($variant['name']) ?>" required></div><div><label class="form-label" for="edit-variant-sku-<?= (int) $variant['id'] ?>">SKU</label><input class="form-input uppercase" id="edit-variant-sku-<?= (int) $variant['id'] ?>" name="sku" value="<?= esc($variant['sku']) ?>" required></div><div><label class="form-label" for="edit-variant-price-<?= (int) $variant['id'] ?>">Harga</label><input class="form-input" id="edit-variant-price-<?= (int) $variant['id'] ?>" name="price" type="number" min="0" step="1" value="<?= esc($variant['price']) ?>" required></div><div><label class="form-label" for="edit-variant-mode-<?= (int) $variant['id'] ?>">Stok</label><select class="form-input" id="edit-variant-mode-<?= (int) $variant['id'] ?>" name="stock_mode"><option value="recipe" <?= $variant['stock_mode'] === 'recipe' ? 'selected' : '' ?>>Resep</option><option value="direct" <?= $variant['stock_mode'] === 'direct' ? 'selected' : '' ?>>Langsung</option><option value="none" <?= $variant['stock_mode'] === 'none' ? 'selected' : '' ?>>Tanpa stok</option></select></div><label class="flex min-h-11 items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" <?= $variant['is_active'] ? 'checked' : '' ?>> Varian aktif</label><div class="sm:col-span-2 lg:col-span-3"><button class="button-secondary" type="submit">Simpan varian</button></div></form><?php endforeach ?></div></div><?php endif ?>
                                </div>
                            </details>
                            <details class="border-t border-border-warm bg-[#FFFCF4]">
                                <summary class="cursor-pointer px-5 py-4 text-sm font-bold focus-ring sm:px-6">Tambah varian harga</summary>
                                <form action="<?= site_url('panel/catalog/variants') ?>" method="post" class="grid gap-4 border-t border-border-warm p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-4">
                                    <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <div><label class="form-label" for="variant-<?= (int) $product['id'] ?>">Nama varian</label><input class="form-input" id="variant-<?= (int) $product['id'] ?>" name="variant_name" required></div>
                                    <div><label class="form-label" for="sku-<?= (int) $product['id'] ?>">SKU</label><input class="form-input uppercase" id="sku-<?= (int) $product['id'] ?>" name="sku" required></div>
                                    <div><label class="form-label" for="price-<?= (int) $product['id'] ?>">Harga</label><input class="form-input" id="price-<?= (int) $product['id'] ?>" name="price" type="number" min="0" step="1" required></div>
                                    <div><label class="form-label" for="mode-<?= (int) $product['id'] ?>">Stok</label><select class="form-input" id="mode-<?= (int) $product['id'] ?>" name="stock_mode"><option value="recipe">Resep</option><option value="direct">Langsung</option><option value="none">Tanpa stok</option></select></div>
                                    <div class="sm:col-span-2 lg:col-span-4"><button class="button-primary" type="submit">Tambahkan varian</button></div>
                                </form>
                            </details>
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
