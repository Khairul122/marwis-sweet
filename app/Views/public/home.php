<!doctype html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Lihat menu dan harga terbaru <?= esc($businessName) ?>.">
    <title><?= esc($businessName) ?> | Menu martabak</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <script src="<?= base_url('assets/js/app.js') ?>" defer></script>
</head>
<body class="min-h-screen bg-surface-cream text-brand-cocoa antialiased">
    <a class="skip-link" href="#konten">Lewati ke konten</a>
    <header class="sticky top-0 z-40 border-b border-border-warm bg-surface-cream/95">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a class="flex items-center gap-3 focus-ring" href="<?= site_url('/') ?>" aria-label="<?= esc($businessName) ?>, halaman utama">
                <span class="label-cut inline-grid h-11 w-11 place-items-center bg-brand-sun font-display text-xl" aria-hidden="true">M</span>
                <span class="font-display text-lg leading-none sm:text-xl"><?= esc($businessName) ?></span>
            </a>
            <nav class="hidden items-center gap-1 sm:flex" aria-label="Navigasi utama">
                <a class="nav-link" href="#menu">Menu</a>
                <a class="nav-link" href="#informasi">Informasi</a>
                <a class="button-secondary ml-2" href="<?= site_url('login') ?>">Masuk panel</a>
            </nav>
            <button class="button-icon sm:hidden" type="button" data-menu-toggle aria-expanded="false" aria-controls="menu-mobile">
                <span class="sr-only">Buka menu</span>
                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
        <nav id="menu-mobile" class="hidden border-t border-border-warm px-4 py-3 sm:hidden" data-menu-mobile aria-label="Navigasi mobile">
            <a class="nav-link block" href="#menu">Menu</a>
            <a class="nav-link block" href="#informasi">Informasi</a>
            <a class="nav-link block" href="<?= site_url('login') ?>">Masuk panel</a>
        </nav>
    </header>

    <main id="konten">
        <section class="overflow-hidden border-b border-brand-cocoa bg-brand-sun">
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[1.12fr_.88fr] lg:px-8 lg:py-24">
                <div>
                    <p class="mb-5 text-sm font-bold uppercase tracking-[0.12em]">Martabak hangat untuk momen dekat</p>
                    <h1 class="max-w-3xl font-display text-5xl leading-[.98] sm:text-6xl lg:text-7xl">Pilih rasa.<br>Siapkan selera.</h1>
                    <p class="mt-6 max-w-xl text-lg leading-8">Temukan menu dan harga terbaru <?= esc($businessName) ?>. Semua pilihan yang tampil di sini sedang tersedia di katalog.</p>
                    <a class="button-primary-inverse mt-8" href="#menu">Lihat menu hari ini</a>
                </div>
                <div class="relative mx-auto w-full max-w-xl" aria-label="Informasi <?= esc($businessName) ?>">
                    <?php if ($banners !== []): ?>
                        <div class="poster-card rotate-[-1deg] overflow-hidden border-2 border-brand-cocoa bg-surface-cream" data-carousel>
                            <div class="relative aspect-[16/10] sm:aspect-[16/9]">
                                <?php foreach ($banners as $index => $banner): ?>
                                    <div class="absolute inset-0 <?= $index === 0 ? '' : 'hidden' ?>" data-carousel-slide>
                                        <?php if ($banner['link_url']): ?><a class="block h-full" href="<?= esc($banner['link_url']) ?>"><?php endif ?>
                                        <img class="h-full w-full object-cover" src="<?= base_url($banner['image_path']) ?>" alt="<?= esc($banner['alt_text']) ?>">
                                        <?php if ($banner['link_url']): ?></a><?php endif ?>
                                    </div>
                                <?php endforeach ?>
                            </div>
                            <?php if (count($banners) > 1): ?><div class="flex items-center justify-between border-t border-brand-cocoa px-4 py-3"><button class="button-secondary" type="button" data-carousel-prev>Banner sebelumnya</button><span class="text-sm font-bold" data-carousel-status>1 / <?= count($banners) ?></span><button class="button-secondary" type="button" data-carousel-next>Banner berikutnya</button></div><?php endif ?>
                        </div>
                    <?php else: ?>
                    <div class="poster-card rotate-[-2deg] border-2 border-brand-cocoa bg-surface-cream p-6 sm:p-8">
                        <p class="font-display text-2xl">Dibuat setelah dipesan</p>
                        <div class="receipt-rule my-6"></div>
                        <div class="grid grid-cols-[1fr_auto] gap-x-6 gap-y-4 text-sm sm:text-base">
                            <span>Adonan hangat</span><strong>Segar</strong>
                            <span>Pilihan rasa</span><strong><?= count($products) > 0 ? count($products) . ' menu' : 'Segera hadir' ?></strong>
                            <span>Harga</span><strong>Jelas</strong>
                        </div>
                        <div class="label-cut mt-7 inline-block bg-brand-chili px-5 py-3 font-bold text-white">Pilih sesuai selera</div>
                    </div>
                    <?php endif ?>
                </div>
            </div>
        </section>

        <section id="menu" class="scroll-mt-24 py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-5 lg:grid-cols-[1fr_auto] lg:items-end">
                    <div>
                        <p class="section-kicker">Menu kami</p>
                        <h2 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Yang enak tidak perlu dibuat rumit.</h2>
                    </div>
                    <?php if ($categories !== []): ?>
                        <div class="flex max-w-full gap-2 overflow-x-auto pb-2" role="group" aria-label="Filter kategori">
                            <button class="filter-chip is-active" type="button" data-category-filter="all" aria-pressed="true">Semua</button>
                            <?php foreach ($categories as $category): ?>
                                <button class="filter-chip" type="button" data-category-filter="<?= esc($category['slug']) ?>" aria-pressed="false"><?= esc($category['name']) ?></button>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
                <?php if ($products !== []): ?><div class="mt-7 max-w-xl"><label class="form-label" for="product-search">Cari menu</label><input class="form-input" id="product-search" type="search" placeholder="Ketik nama produk atau varian" data-product-search></div><?php endif ?>

                <?php if ($products === []): ?>
                    <div class="mt-10 border border-border-warm bg-surface-soft-yellow p-8 sm:p-12">
                        <p class="font-display text-2xl">Menu sedang disiapkan.</p>
                        <p class="mt-3 max-w-xl text-ink-muted">Katalog belum memiliki produk aktif. Silakan kembali lagi setelah menu terbaru diterbitkan.</p>
                    </div>
                <?php else: ?>
                    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" data-product-grid>
                        <?php foreach ($products as $index => $product): ?>
                            <?php $productVariants = $variantsByProduct[$product['id']] ?? []; ?>
                            <article class="product-card group" data-product-card data-category="<?= esc($product['category_slug']) ?>" data-search="<?= esc(mb_strtolower($product['name'] . ' ' . implode(' ', array_column($productVariants, 'name')))) ?>">
                                <div class="product-visual <?= $index % 3 === 1 ? 'bg-brand-sun' : ($index % 3 === 2 ? 'bg-brand-cocoa text-surface-cream' : 'bg-surface-soft-yellow') ?>">
                                    <?php if (!empty($product['image_path'])): ?>
                                        <img src="<?= base_url($product['image_path']) ?>" alt="<?= esc($product['name']) ?>" class="h-full w-full object-cover" loading="lazy">
                                    <?php else: ?>
                                        <span class="font-display text-6xl opacity-20" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($product['name'], 0, 1))) ?></span>
                                        <span class="absolute bottom-4 left-4 text-xs font-bold uppercase tracking-wider">Foto segera hadir</span>
                                    <?php endif ?>
                                </div>
                                <div class="p-5 sm:p-6">
                                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-brand-chili"><?= esc($product['category_name']) ?></p>
                                    <h3 class="mt-2 font-display text-2xl leading-tight"><?= esc($product['name']) ?></h3>
                                    <?php if ($product['description']): ?>
                                        <p class="mt-3 line-clamp-2 text-sm leading-6 text-ink-muted"><?= esc($product['description']) ?></p>
                                    <?php endif ?>
                                    <div class="receipt-rule my-5"></div>
                                    <?php if ($productVariants === []): ?>
                                        <p class="text-sm font-semibold text-ink-muted">Harga belum tersedia</p>
                                    <?php else: ?>
                                        <ul class="space-y-3" aria-label="Pilihan dan harga <?= esc($product['name']) ?>">
                                            <?php foreach ($productVariants as $variant): ?>
                                                <li class="flex items-baseline justify-between gap-4"><span class="text-sm font-semibold"><?= esc($variant['name']) ?></span><strong class="tabular-nums">Rp<?= number_format((float) $variant['price'], 0, ',', '.') ?></strong></li>
                                            <?php endforeach ?>
                                        </ul>
                                    <?php endif ?>
                                    <a class="mt-5 inline-flex min-h-11 items-center font-bold underline decoration-brand-sun decoration-4 underline-offset-4" href="<?= site_url('menu/' . $product['slug']) ?>">Lihat detail produk</a>
                                </div>
                            </article>
                        <?php endforeach ?>
                    </div>
                    <p class="mt-8 hidden border border-border-warm bg-white p-6 text-ink-muted" data-filter-empty>Menu yang dicari belum ditemukan. Coba kata lain atau pilih semua kategori.</p>
                <?php endif ?>
            </div>
        </section>

        <section id="informasi" class="scroll-mt-24 border-y border-brand-cocoa bg-brand-cocoa py-14 text-surface-cream sm:py-20">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[.8fr_1.2fr] lg:px-8">
                <h2 class="font-display text-4xl leading-tight sm:text-5xl">Menu jujur,<br>harga terbaca.</h2>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div><h3 class="font-bold text-brand-sun">Katalog langsung</h3><p class="mt-2 leading-7 text-[#E8D9BD]">Produk dan harga berasal dari panel usaha, sehingga pembaruan katalog tidak perlu mengubah halaman secara manual.</p></div>
                    <div><h3 class="font-bold text-brand-sun">Pilihan yang tersedia</h3><p class="mt-2 leading-7 text-[#E8D9BD]">Menu nonaktif tidak ditampilkan. Pelanggan hanya melihat pilihan yang sedang diterbitkan oleh pemilik.</p></div>
                    <?php if (!empty($settings['opening_hours'])): ?><div><h3 class="font-bold text-brand-sun">Jam buka</h3><p class="mt-2 leading-7 text-[#E8D9BD]"><?= esc($settings['opening_hours']) ?></p></div><?php endif ?>
                    <?php if (!empty($settings['address'])): ?><div><h3 class="font-bold text-brand-sun">Alamat</h3><p class="mt-2 leading-7 text-[#E8D9BD]"><?= esc($settings['address']) ?></p></div><?php endif ?>
                    <?php if (!empty($settings['phone'])): ?><div><h3 class="font-bold text-brand-sun">Kontak</h3><p class="mt-2 leading-7 text-[#E8D9BD]"><a class="underline decoration-brand-sun decoration-2 underline-offset-4" href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $settings['phone'])) ?>"><?= esc($settings['phone']) ?></a></p></div><?php endif ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-surface-cream"><div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-8 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"><p class="font-bold"><?= esc($businessName) ?></p><a class="font-semibold underline decoration-brand-sun decoration-4 underline-offset-4" href="<?= site_url('login') ?>">Akses panel usaha</a></div></footer>
</body>
</html>
