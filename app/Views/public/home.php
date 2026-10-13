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
<?php
$phone = $settings['phone'] ?? '';
$phoneDigits = preg_replace('/[^0-9+]/', '', $phone);
$waNumber = preg_replace('/^0/', '62', preg_replace('/\D/', '', $phone));
$address = $settings['address'] ?? '';
$hours = $settings['opening_hours'] ?? '';
?>
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
                <a class="nav-link" href="#kontak">Kontak</a>
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
            <a class="nav-link block" href="#kontak">Kontak</a>
            <a class="nav-link block" href="<?= site_url('login') ?>">Masuk panel</a>
        </nav>
    </header>

    <main id="konten">
        <section class="overflow-hidden border-b border-brand-cocoa bg-brand-sun">
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[1.12fr_.88fr] lg:px-8 lg:py-24">
                <div>
                    <p class="label-cut mb-6 inline-block bg-brand-cocoa px-4 py-2 pr-7 text-xs font-bold uppercase tracking-[0.12em] text-brand-sun">Martabak dibuat setelah dipesan</p>
                    <h1 class="max-w-3xl font-display text-5xl leading-[.98] sm:text-6xl lg:text-7xl">Pilih rasa.<br>Siapkan selera.</h1>
                    <p class="mt-6 max-w-xl text-lg leading-8">Menu dan harga terbaru <?= esc($businessName) ?>. Semua pilihan yang tampil di sini sedang tersedia.</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a class="button-primary-inverse px-6" href="#menu">Lihat menu hari ini</a>
                        <?php if ($waNumber !== ''): ?><a class="button-secondary px-6" href="https://wa.me/<?= esc($waNumber) ?>" rel="noopener">Pesan via WhatsApp</a>
                        <?php elseif ($phoneDigits !== ''): ?><a class="button-secondary px-6" href="tel:<?= esc($phoneDigits) ?>">Hubungi kami</a><?php endif ?>
                    </div>
                    <dl class="mt-10 flex flex-wrap gap-x-8 gap-y-4 border-t-2 border-dashed border-brand-cocoa/40 pt-6 text-sm">
                        <?php if ($products !== []): ?><div><dt class="text-xs font-bold uppercase tracking-[0.12em]">Pilihan menu</dt><dd class="mt-1 font-display text-2xl"><?= count($products) ?></dd></div><?php endif ?>
                        <?php if (count($categories) > 0): ?><div><dt class="text-xs font-bold uppercase tracking-[0.12em]">Kategori</dt><dd class="mt-1 font-display text-2xl"><?= count($categories) ?></dd></div><?php endif ?>
                        <?php if ($hours !== ''): ?><div><dt class="text-xs font-bold uppercase tracking-[0.12em]">Jam buka</dt><dd class="mt-1 text-base font-semibold"><?= esc($hours) ?></dd></div><?php endif ?>
                    </dl>
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
                                    <?php if (!empty($product['is_featured'])): ?><span class="label-cut absolute left-0 top-4 bg-brand-chili py-1.5 pl-4 pr-8 text-xs font-bold uppercase tracking-wider text-white">Unggulan</span><?php endif ?>
                                </div>
                                <div class="p-5 sm:p-6">
                                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-brand-chili"><?= esc($product['category_name']) ?></p>
                                    <h3 class="mt-2 font-display text-2xl leading-tight"><?= esc($product['name']) ?></h3>
                                    <?php if ($productVariants !== []): ?><p class="mt-2 text-sm font-semibold text-ink-muted">Mulai <span class="tabular-nums text-brand-cocoa">Rp<?= number_format((float) min(array_column($productVariants, 'price')), 0, ',', '.') ?></span></p><?php endif ?>
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
                <div>
                    <p class="section-kicker !text-brand-sun">Tentang <?= esc($businessName) ?></p>
                    <h2 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Menu jujur,<br>harga terbaca.</h2>
                </div>
                <div class="grid gap-px border border-brand-sun/30 bg-brand-sun/30 sm:grid-cols-3">
                    <div class="bg-brand-cocoa p-6"><h3 class="font-bold text-brand-sun">Dibuat setelah dipesan</h3><p class="mt-2 leading-7 text-[#E8D9BD]">Adonan dimasak saat pesanan masuk, supaya tiba hangat dan tidak lembek.</p></div>
                    <div class="bg-brand-cocoa p-6"><h3 class="font-bold text-brand-sun">Harga jelas</h3><p class="mt-2 leading-7 text-[#E8D9BD]">Setiap pilihan tercantum lengkap dengan harganya, tanpa kejutan di kasir.</p></div>
                    <div class="bg-brand-cocoa p-6"><h3 class="font-bold text-brand-sun">Selalu terbaru</h3><p class="mt-2 leading-7 text-[#E8D9BD]">Menu yang tidak dijual otomatis disembunyikan dari halaman ini.</p></div>
                </div>
            </div>
        </section>

        <?php if ($hours !== '' || $address !== '' || $phone !== ''): ?>
        <section id="kontak" class="scroll-mt-24 py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="section-kicker">Kunjungi &amp; hubungi</p>
                <h2 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Mampir atau pesan sekarang.</h2>
                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    <?php if ($hours !== ''): ?><div class="border border-border-warm bg-white p-6"><p class="section-kicker">Jam buka</p><p class="mt-3 text-lg font-semibold leading-8"><?= esc($hours) ?></p></div><?php endif ?>
                    <?php if ($address !== ''): ?><div class="border border-border-warm bg-white p-6"><p class="section-kicker">Alamat</p><p class="mt-3 text-lg font-semibold leading-8"><?= esc($address) ?></p><a class="mt-4 inline-flex min-h-11 items-center font-bold underline decoration-brand-sun decoration-4 underline-offset-4" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($address) ?>" target="_blank" rel="noopener">Buka di peta<span class="sr-only"> (tab baru)</span></a></div><?php endif ?>
                    <?php if ($phone !== ''): ?><div class="border border-border-warm bg-white p-6"><p class="section-kicker">Kontak</p><p class="mt-3 text-lg font-semibold leading-8"><a href="tel:<?= esc($phoneDigits) ?>"><?= esc($phone) ?></a></p><?php if ($waNumber !== ''): ?><a class="button-primary mt-4" href="https://wa.me/<?= esc($waNumber) ?>" rel="noopener">Chat WhatsApp</a><?php endif ?></div><?php endif ?>
                </div>
            </div>
        </section>
        <?php endif ?>
    </main>

    <footer class="border-t border-brand-cocoa bg-surface-cream">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr] lg:px-8">
            <div><p class="font-display text-xl"><?= esc($businessName) ?></p><p class="mt-3 max-w-sm text-sm leading-6 text-ink-muted">Martabak hangat dengan menu dan harga yang selalu diperbarui.</p></div>
            <nav aria-label="Navigasi footer"><p class="section-kicker">Jelajahi</p><ul class="mt-3 space-y-1 text-sm font-semibold"><li><a class="inline-flex min-h-11 items-center hover:underline" href="#menu">Menu</a></li><li><a class="inline-flex min-h-11 items-center hover:underline" href="#informasi">Informasi</a></li><?php if ($hours !== '' || $address !== '' || $phone !== ''): ?><li><a class="inline-flex min-h-11 items-center hover:underline" href="#kontak">Kontak</a></li><?php endif ?></ul></nav>
            <div><p class="section-kicker">Pegawai</p><a class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold underline decoration-brand-sun decoration-4 underline-offset-4" href="<?= site_url('login') ?>">Masuk panel usaha</a></div>
        </div>
        <div class="border-t border-border-warm px-4 py-4 text-center text-xs text-ink-muted">&copy; <?= date('Y') ?> <?= esc($businessName) ?>. Semua hak dilindungi.</div>
    </footer>
</body>
</html>
