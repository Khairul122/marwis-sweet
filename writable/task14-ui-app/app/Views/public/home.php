<?php
/**
 * Landing publik. Semua angka, harga, ulasan, dan kontak berasal dari database; bagian yang datanya kosong tidak ditampilkan.
 * Data toko, kontak, dan katalog keranjang disiapkan Home::storefront().
 *
 * @var string $businessName
 * @var array $storeReviews
 */
$hasReviews = (int) ($storeReviews['total'] ?? 0) > 0;
$avgRating = number_format((float) ($storeReviews['average'] ?? 0), 1, ',', '.');

$minPrice = null;
foreach ($variantsByProduct as $list) {
    foreach ($list as $v) {
        $minPrice = $minPrice === null ? (float) $v['price'] : min($minPrice, (float) $v['price']);
    }
}

$perks = [
    ['Dimasak saat dipesan', 'Adonan baru dituang ke wajan setelah pesananmu masuk, jadi martabak sampai masih hangat.', sf_icon('flame')],
    ['Topping pilih sendiri', $toppings !== [] ? count($toppings) . ' pilihan topping bisa ditambahkan ke setiap porsi martabak.' : 'Racik rasa favoritmu dari pilihan menu yang tersedia.', sf_icon('cookie')],
    ['Harga jelas di depan', 'Setiap varian dan topping tertulis harganya. Total pesanan terlihat sebelum kamu kirim.', sf_icon('receipt-text')],
    [$waNumber !== '' ? 'Pesan lewat WhatsApp' : 'Ambil di gerai', $waNumber !== '' ? 'Susun pesanan di sini, kirim sekali klik ke WhatsApp kami.' : 'Susun pesanan di sini, lalu tunjukkan ke kasir saat tiba.', $waNumber !== '' ? sf_wa_icon('h-6 w-6') : sf_icon('shopping-bag')],
];
?><!doctype html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= esc($businessName) ?>: martabak manis dan telur yang dimasak saat dipesan. Lihat menu dan harga, pilih topping, lalu pesan lewat WhatsApp.">
    <meta property="og:title" content="<?= esc($businessName) ?> | Martabak hangat langsung dari wajan">
    <meta property="og:image" content="<?= base_url('assets/img/martabak-manis-hero.jpg') ?>">
<?= $this->include('public/partials/head') ?>
</head>
<body class="min-h-screen bg-white pb-20 font-jakarta text-brand-cocoa antialiased sm:pb-0">
    <a class="skip-link" href="#konten">Lewati ke konten</a>
<?= $this->include('public/partials/header') ?>

    <main id="konten">
        <!-- Hero: pita warna diagonal, piring martabak melayang, kartu kertas menumpang di tepi sobek -->
        <?php
        $heroPicks = array_slice(array_values(array_filter($products, static fn (array $p): bool => !empty($p['image_path']) && !empty($p['is_featured']) && !empty($variantsByProduct[$p['id']]))), 0, 2);
        $sprinkle = static fn (string $pos, int $deg): string => '<span class="pointer-events-none absolute h-2 w-6 rounded-full bg-brand-cocoa ' . $pos . '" style="rotate:' . $deg . 'deg" aria-hidden="true"></span>';
        $leaf = static fn (string $cls): string => '<svg aria-hidden="true" viewBox="0 0 64 64" class="pointer-events-none absolute ' . $cls . '"><path d="M4 60C4 28 24 6 60 4c0 34-22 56-56 56Z" fill="#2E9A5A"/><path d="M8 56 56 8" stroke="#1F6E3F" stroke-width="3" stroke-linecap="round"/></svg>';
        ?>
        <section class="relative bg-white">
            <div class="relative overflow-hidden bg-[linear-gradient(165deg,var(--color-brand-orange)_0_56%,var(--color-brand-sun)_56%_80%,var(--color-brand-pandan)_80%)] pb-36 lg:bg-[linear-gradient(110deg,var(--color-brand-orange)_0_47%,var(--color-brand-sun)_47%_75%,var(--color-brand-pandan)_75%)] lg:pb-40">
                <?= $leaf('-left-6 top-1/3 h-20 w-20 -rotate-12 opacity-90 blur-[1px] lg:h-24 lg:w-24') ?>
                <?= $leaf('right-4 top-6 hidden h-14 w-14 rotate-45 lg:block') ?>
                <?= $leaf('bottom-24 right-[46%] hidden h-10 w-10 rotate-[100deg] blur-[2px] lg:block') ?>
                <?= $sprinkle('left-[44%] top-16 hidden lg:block', 35) ?>
                <?= $sprinkle('right-10 top-1/2 hidden lg:block', -20) ?>
                <?= $sprinkle('left-[52%] bottom-40 hidden lg:block', 70) ?>
                <?= $sprinkle('left-8 bottom-44', -40) ?>
                <span class="pointer-events-none absolute right-[30%] top-10 hidden h-4 w-5 rounded-[50%] bg-[#E9C27A] lg:block" aria-hidden="true"></span>
                <span class="pointer-events-none absolute bottom-52 left-[40%] hidden h-3 w-4 rotate-12 rounded-[50%] bg-[#FFF3C4] lg:block" aria-hidden="true"></span>

                <div class="relative mx-auto grid max-w-7xl items-center gap-6 px-4 pt-10 sm:px-6 sm:pt-14 lg:grid-cols-[1fr_1.1fr] lg:gap-4 lg:px-8 lg:pt-16">
                    <div class="reveal relative z-10">
                        <p class="font-brand text-lg font-extrabold uppercase tracking-[0.08em] text-brand-cocoa sm:text-xl">Rasakan nikmatnya</p>
                        <h1 class="font-brand font-extrabold uppercase leading-[.85] text-brand-cocoa">
                            <span class="block text-[4.25rem] sm:text-8xl lg:text-[7.5rem]">Martabak</span>
                            <span class="mt-2 inline-block -rotate-3 text-5xl text-white [paint-order:stroke_fill] [-webkit-text-stroke:6px_var(--color-brand-cocoa)] [text-shadow:5px_5px_0_var(--color-brand-cocoa)] sm:text-7xl lg:text-[5.25rem]">Manis &amp; Telur</span>
                        </h1>
                        <p class="mt-6 max-w-md text-base font-semibold leading-7 text-brand-cocoa sm:text-lg">
                            <?php if ($products === []): ?>Menu sedang disiapkan. Kembali sebentar lagi, ya.
                            <?php else: ?>Tebal, lembut, topping melimpah. Dimasak setelah kamu pesan, lalu <?= $waNumber !== '' ? 'pesan cukup lewat WhatsApp' : 'tinggal ambil di gerai' ?>.<?php endif ?>
                        </p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <a class="lp-btn lp-btn-sun ring-2 ring-brand-cocoa" href="#menu">Lihat menu<?= $minPrice !== null ? ' · mulai ' . sf_rp($minPrice) : '' ?></a>
                            <?php if ($waChat !== ''): ?><a class="lp-btn lp-btn-dark" href="<?= esc($waChat) ?>" target="_blank" rel="noopener"><?= sf_wa_icon() ?>Pesan via WhatsApp<span class="sr-only"> (tab baru)</span></a><?php endif ?>
                        </div>
                    </div>

                    <div class="reveal relative mx-auto w-full max-w-xl lg:max-w-none lg:translate-x-6">
                        <img class="relative w-full rotate-[-4deg] drop-shadow-[0_30px_30px_rgb(45_27_18/.45)]" src="<?= base_url('assets/img/martabak-piring.webp') ?>" alt="Seporsi martabak manis isi cokelat, keju, dan kacang di atas piring" fetchpriority="high" width="1400" height="941">
                        <?php if ($minPrice !== null): ?>
                            <p class="absolute -top-2 right-2 grid h-24 w-24 rotate-12 place-items-center rounded-full bg-brand-chili text-center text-white shadow-xl ring-4 ring-white sm:h-28 sm:w-28 lg:right-10"><span class="leading-tight"><span class="block text-[11px] font-bold uppercase tracking-wider">Mulai</span><span class="block font-brand text-2xl font-extrabold tabular-nums sm:text-[1.7rem]"><?= esc(str_replace('Rp', '', sf_rp($minPrice))) ?></span><span class="block text-[11px] font-semibold">rupiah</span></span></p>
                        <?php endif ?>
                    </div>
                </div>

                <svg aria-hidden="true" class="absolute inset-x-0 bottom-0 h-10 w-full text-white sm:h-14" viewBox="0 0 1200 60" preserveAspectRatio="none"><path fill="currentColor" d="M0 34 40 22 70 38 120 18 160 32 205 14 250 36 300 20 345 40 390 16 440 30 480 12 530 34 575 22 620 40 665 18 710 32 760 14 800 36 850 20 895 38 940 16 985 30 1030 12 1075 34 1120 20 1160 38 1200 24V60H0Z"/></svg>
            </div>

            <?php if ($heroPicks !== [] || $hasReviews || $hours !== ''): ?>
                <div class="relative z-10 mx-auto -mt-28 max-w-6xl px-4 pb-6 sm:px-6 lg:px-8">
                    <div class="reveal grid -rotate-1 gap-5 rounded-xl border border-border-warm bg-white p-5 shadow-[0_24px_50px_-20px_rgb(45_27_18/.45)] sm:p-6 md:grid-cols-2 lg:grid-cols-[1fr_1fr_.75fr] lg:divide-x lg:divide-dashed lg:divide-border-warm">
                        <?php foreach ($heroPicks as $i => $pick): ?>
                            <a class="group flex items-center gap-4 rounded-lg <?= $i > 0 ? 'lg:pl-5' : '' ?> lg:pr-5" href="<?= site_url('menu/' . $pick['slug']) ?>">
                                <img class="h-20 w-20 shrink-0 rounded-full object-cover ring-4 ring-brand-sun transition group-hover:rotate-6 sm:h-24 sm:w-24" src="<?= base_url($pick['image_path']) ?>" alt="" loading="lazy">
                                <span class="min-w-0">
                                    <span class="block text-[11px] font-extrabold uppercase tracking-[0.12em] text-brand-chili">Menu unggulan</span>
                                    <span class="block font-brand text-xl font-extrabold leading-tight group-hover:underline"><?= esc($pick['name']) ?></span>
                                    <span class="mt-0.5 block text-sm text-ink-muted">Mulai <strong class="tabular-nums text-brand-cocoa"><?= sf_rp(min(array_column($variantsByProduct[$pick['id']], 'price'))) ?></strong></span>
                                </span>
                            </a>
                        <?php endforeach ?>
                        <div class="flex flex-col justify-center <?= $heroPicks !== [] ? 'lg:pl-5' : '' ?> <?= count($heroPicks) === 1 ? '' : 'md:col-span-2 lg:col-span-1' ?>">
                            <?php if ($hasReviews): ?><p class="flex items-baseline gap-2"><span class="font-brand text-4xl font-extrabold leading-none"><?= $avgRating ?></span><span class="text-brand-sun-hover" aria-hidden="true"><?= sf_stars((int) round((float) $storeReviews['average'])) ?></span></p><p class="text-sm text-ink-muted">dari <?= (int) $storeReviews['total'] ?> ulasan pembeli</p><?php endif ?>
                            <?php if ($hours !== ''): ?><p class="mt-2 text-sm font-bold"><span class="mr-1.5 inline-block h-2 w-2 rounded-full bg-brand-pandan align-middle" aria-hidden="true"></span>Buka <?= esc($hours) ?></p><?php endif ?>
                        </div>
                    </div>
                </div>
            <?php endif ?>
        </section>

        <!-- Keunggulan -->
        <section class="bg-brand-cocoa py-14 text-surface-cream sm:py-20" aria-labelledby="keunggulan-title">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 id="keunggulan-title" class="reveal max-w-2xl font-brand text-3xl font-extrabold leading-tight sm:text-4xl">Kenapa martabak kami <span class="text-brand-sun">selalu dicari lagi</span></h2>
                <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <?php foreach ($perks as [$title, $text, $svg]): ?>
                        <li class="reveal rounded-3xl bg-white/[.06] p-6 ring-1 ring-white/10">
                            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-sun text-brand-cocoa"><?= $svg ?></span>
                            <h3 class="mt-5 font-brand text-xl font-bold"><?= esc($title) ?></h3>
                            <p class="mt-2 text-sm leading-6 text-[#E8D9BD]"><?= esc($text) ?></p>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        </section>

        <?php if ($banners !== []): ?>
        <!-- Promo dari panel -->
        <section class="mx-auto max-w-7xl px-4 pt-14 sm:px-6 sm:pt-20 lg:px-8" aria-label="Promo">
            <div data-carousel>
                <div class="relative aspect-[16/7] overflow-hidden rounded-3xl shadow-lg sm:aspect-[16/5]">
                    <?php foreach ($banners as $index => $banner): ?>
                        <div class="absolute inset-0 <?= $index === 0 ? '' : 'hidden' ?>" data-carousel-slide>
                            <?php if ($banner['link_url']): ?><a class="block h-full" href="<?= esc($banner['link_url']) ?>"><?php endif ?>
                            <img class="h-full w-full object-cover" src="<?= base_url($banner['image_path']) ?>" alt="<?= esc($banner['alt_text']) ?>" <?= $index === 0 ? '' : 'loading="lazy"' ?>>
                            <?php if ($banner['link_url']): ?></a><?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
                <?php if (count($banners) > 1): ?>
                    <div class="mt-4 flex items-center justify-center gap-3">
                        <button class="grid h-11 w-11 place-items-center rounded-full border border-border-warm bg-white hover:border-brand-cocoa" type="button" data-carousel-prev aria-label="Promo sebelumnya"><?= sf_icon('chevron-left', 'h-5 w-5') ?></button>
                        <span class="min-w-14 text-center text-sm font-bold tabular-nums" data-carousel-status>1 / <?= count($banners) ?></span>
                        <button class="grid h-11 w-11 place-items-center rounded-full border border-border-warm bg-white hover:border-brand-cocoa" type="button" data-carousel-next aria-label="Promo berikutnya"><?= sf_icon('chevron-right', 'h-5 w-5') ?></button>
                    </div>
                <?php endif ?>
            </div>
        </section>
        <?php endif ?>

        <!-- Menu -->
        <section id="menu" class="scroll-mt-20 py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <p class="lp-eyebrow">Daftar menu</p>
                    <h2 class="lp-title mt-4">Mau rasa apa hari ini?</h2>
                    <p class="mt-4 text-ink-muted">Pilih ukuran atau varian, harga langsung berubah. Tekan <strong class="text-brand-cocoa">Tambah</strong> untuk memasukkan ke pesanan.</p>
                </div>

                <?php if ($products === []): ?>
                    <div class="lp-card mx-auto mt-10 max-w-xl p-8 text-center sm:p-12">
                        <p class="font-brand text-2xl font-bold">Menu sedang disiapkan.</p>
                        <p class="mt-3 text-ink-muted">Belum ada produk aktif di katalog. Silakan kembali setelah menu terbaru terbit.</p>
                    </div>
                <?php else: ?>
                    <div class="mt-10 flex flex-col items-center gap-4">
                        <?php if ($categories !== []): ?>
                            <div class="flex max-w-full gap-2 overflow-x-auto px-1 pb-2" role="group" aria-label="Filter kategori">
                                <button class="lp-chip is-active" type="button" data-category-filter="all" aria-pressed="true">Semua</button>
                                <?php foreach ($categories as $category): ?><button class="lp-chip" type="button" data-category-filter="<?= esc($category['slug']) ?>" aria-pressed="false"><?= esc($category['name']) ?></button><?php endforeach ?>
                            </div>
                        <?php endif ?>
                        <div class="relative w-full max-w-md">
                            <label class="sr-only" for="product-search">Cari menu</label>
                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-muted"><?= sf_icon('search', 'h-5 w-5') ?></span>
                            <input class="min-h-12 w-full rounded-full border border-border-warm bg-white pl-12 pr-5 text-base placeholder:text-ink-muted/70 hover:border-ink-muted focus:border-brand-cocoa" id="product-search" type="search" placeholder="Cari menu, misal: keju" data-product-search>
                        </div>
                    </div>

                    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-product-grid>
                        <?php foreach ($products as $product): ?>
                            <?php $variants = $variantsByProduct[$product['id']] ?? []; $rating = $ratings[$product['id']] ?? null; ?>
                            <article class="reveal lp-card group flex flex-col overflow-hidden transition duration-200 hover:-translate-y-1 hover:shadow-[0_24px_50px_-24px_rgb(45_27_18/.5)]" data-product-card data-card data-product-id="<?= (int) $product['id'] ?>" data-category="<?= esc($product['category_slug']) ?>" data-search="<?= esc(mb_strtolower($product['name'] . ' ' . implode(' ', array_column($variants, 'name')))) ?>">
                                <div class="relative grid aspect-[4/3] place-items-center overflow-hidden bg-surface-soft-yellow">
                                    <?php if (!empty($product['image_path'])): ?>
                                        <img src="<?= base_url($product['image_path']) ?>" alt="<?= esc($product['name']) ?>" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                                    <?php else: ?>
                                        <span class="font-brand text-7xl font-extrabold text-brand-sun-hover/40" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($product['name'], 0, 1))) ?></span>
                                    <?php endif ?>
                                    <span class="absolute left-4 top-4 rounded-full bg-white/95 px-3 py-1 text-xs font-bold shadow-sm"><?= esc($product['category_name']) ?></span>
                                    <?php if (!empty($product['is_featured'])): ?><span class="absolute right-4 top-4 rounded-full bg-brand-chili px-3 py-1 text-xs font-bold text-white shadow-sm">Unggulan</span><?php endif ?>
                                </div>
                                <div class="flex flex-1 flex-col p-5 sm:p-6">
                                    <h3 class="font-brand text-2xl font-bold leading-tight"><?= esc($product['name']) ?></h3>
                                    <?php if ($rating): ?><p class="mt-1 text-sm text-ink-muted"><span aria-hidden="true" class="text-brand-sun-hover">★</span> <span class="font-bold text-brand-cocoa"><?= number_format($rating['average'], 1, ',', '.') ?></span> · <?= $rating['total'] ?> ulasan</p><?php endif ?>
                                    <?php if ($product['description']): ?><p class="mt-2 line-clamp-2 text-sm leading-6 text-ink-muted"><?= esc($product['description']) ?></p><?php endif ?>
                                    <div class="mt-4"><?= sf_variant_picker($product, $variants) ?></div>
                                    <div class="mt-5 flex items-center justify-between gap-3 pt-1 lg:mt-auto">
                                        <?php if ($variants !== []): ?>
                                            <p class="font-brand text-2xl font-extrabold tabular-nums" data-price-out><?= sf_rp($variants[0]['price']) ?></p>
                                            <button class="lp-btn lp-btn-sun !min-h-11 !px-5" type="button" data-add>+ Tambah</button>
                                        <?php endif ?>
                                    </div>
                                    <a class="mt-3 inline-flex min-h-11 items-center self-start text-sm font-bold text-ink-muted underline decoration-brand-sun decoration-2 underline-offset-4 hover:text-brand-cocoa" href="<?= site_url('menu/' . $product['slug']) ?>">Lihat detail<span class="sr-only"> <?= esc($product['name']) ?></span></a>
                                </div>
                            </article>
                        <?php endforeach ?>
                    </div>
                    <p class="lp-card mt-8 hidden p-6 text-center text-ink-muted" data-filter-empty role="status">Menu yang dicari belum ditemukan. Coba kata lain atau pilih semua kategori.</p>
                <?php endif ?>
            </div>
        </section>

        <?php if ($toppings !== []): ?>
        <!-- Topping: foto dicocokkan dari nama topping (sf_topping_image) -->
        <section id="topping" class="relative scroll-mt-20 overflow-hidden bg-brand-sun py-16 sm:py-24">
            <span class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-white/25" aria-hidden="true"></span>
            <span class="pointer-events-none absolute -bottom-28 -right-20 h-80 w-80 rounded-full bg-brand-sun-hover/60" aria-hidden="true"></span>
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <p class="lp-eyebrow !bg-white/70">Topping</p>
                    <h2 class="lp-title mt-4">Makin penuh, makin puas.</h2>
                    <p class="mt-4 leading-7">Pilih topping saat menyusun pesanan. Harganya dihitung per porsi martabak dan langsung masuk ke total.</p>
                </div>
                <div class="reveal mx-auto mt-10 max-w-5xl"><?= sf_topping_cards($toppings) ?></div>
            </div>
        </section>
        <?php endif ?>

        <!-- Cara pesan -->
        <section id="cara-pesan" class="scroll-mt-20 py-16 sm:py-24">
            <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
                <div class="reveal relative">
                    <img class="aspect-[4/3] w-full rounded-[2rem] object-cover shadow-xl" src="<?= base_url('assets/img/martabak-proses.jpg') ?>" alt="Adonan martabak manis di wajan besi, ditaburi meses cokelat dan mentega" loading="lazy" width="1400" height="1045">
                    <p class="absolute -bottom-5 left-6 rounded-2xl bg-brand-cocoa px-5 py-3 font-brand text-lg font-bold text-white shadow-xl">Fresh dari wajan, bukan stok kemarin</p>
                </div>
                <div class="reveal">
                    <p class="lp-eyebrow">Cara pesan</p>
                    <h2 class="lp-title mt-4">Pesan martabak cuma tiga langkah.</h2>
                    <ol class="mt-8 space-y-6">
                        <li class="flex gap-4"><span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-brand-sun font-brand text-xl font-extrabold">1</span><div><h3 class="font-brand text-xl font-bold">Pilih menu</h3><p class="mt-1 leading-7 text-ink-muted">Cari rasa favorit, pilih varian, lalu tekan <strong class="text-brand-cocoa">Tambah</strong>.</p></div></li>
                        <li class="flex gap-4"><span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-brand-sun font-brand text-xl font-extrabold">2</span><div><h3 class="font-brand text-xl font-bold">Atur pesanan</h3><p class="mt-1 leading-7 text-ink-muted">Buka keranjang, ubah jumlah<?= $toppings !== [] ? ', dan tambah topping per porsi' : '' ?>. Total langsung terhitung.</p></div></li>
                        <li class="flex gap-4"><span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-brand-sun font-brand text-xl font-extrabold">3</span><div><h3 class="font-brand text-xl font-bold"><?= $waNumber !== '' ? 'Kirim ke WhatsApp' : 'Tunjukkan di gerai' ?></h3><p class="mt-1 leading-7 text-ink-muted"><?= $waNumber !== '' ? 'Pesanan terkirim rapi ke WhatsApp kami. Tinggal tunggu konfirmasi.' : 'Salin pesanan dan tunjukkan ke kasir saat kamu tiba.' ?></p></div></li>
                    </ol>
                    <button class="lp-btn lp-btn-dark mt-9" type="button" data-order-open><?= sf_icon('shopping-bag', 'h-5 w-5') ?>Buka pesanan saya</button>
                </div>
            </div>
        </section>

        <?php if ($reviews !== []): ?>
        <!-- Ulasan: hanya ulasan yang sudah disetujui moderator -->
        <section id="ulasan" class="scroll-mt-20 border-t border-border-warm bg-white py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="lp-eyebrow">Kata pembeli</p>
                        <h2 class="lp-title mt-4">Yang sudah coba, balik lagi.</h2>
                    </div>
                    <div class="flex items-center gap-4">
                        <p class="font-brand text-6xl font-extrabold leading-none tabular-nums"><?= $avgRating ?></p>
                        <div><p class="text-lg text-brand-sun-hover" aria-hidden="true"><?= sf_stars((int) round((float) $storeReviews['average'])) ?></p><p class="text-sm text-ink-muted">dari <?= (int) $storeReviews['total'] ?> ulasan</p></div>
                    </div>
                </div>
                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($reviews as $review): ?>
                        <?php $who = $review['display_name'] ?: 'Pembeli'; ?>
                        <figure class="reveal lp-card flex flex-col p-6">
                            <p class="text-brand-sun-hover" role="img" aria-label="<?= (int) $review['rating'] ?> dari 5 bintang"><?= sf_stars((int) $review['rating']) ?></p>
                            <blockquote class="mt-3 flex-1 leading-7">“<?= esc($review['comment']) ?>”</blockquote>
                            <figcaption class="mt-5 flex items-center gap-3 border-t border-border-warm pt-4 text-sm">
                                <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-sun font-bold" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($who, 0, 1))) ?></span>
                                <span><strong class="block"><?= esc($who) ?></strong><span class="text-ink-muted">pesan <?= esc($review['product_name']) ?></span></span>
                            </figcaption>
                        </figure>
                    <?php endforeach ?>
                </div>
            </div>
        </section>
        <?php endif ?>

        <?php if ($hasContact): ?>
        <!-- Lokasi -->
        <section id="lokasi" class="scroll-mt-20 py-16 sm:py-24">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[.9fr_1.1fr] lg:px-8">
                <div class="reveal">
                    <p class="lp-eyebrow">Lokasi &amp; jam buka</p>
                    <h2 class="lp-title mt-4">Mampir langsung, atau pesan dari rumah.</h2>
                    <dl class="mt-8 space-y-4">
                        <?php if ($address !== ''): ?>
                            <div class="lp-card flex gap-4 p-5"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-brand-sun"><?= sf_icon('map-pin') ?></span>
                                <div><dt class="text-xs font-bold uppercase tracking-[0.12em] text-ink-muted">Alamat</dt><dd class="mt-1 font-semibold leading-7"><?= esc($address) ?></dd>
                                <dd><a class="mt-1 inline-flex min-h-11 items-center text-sm font-bold text-brand-chili underline underline-offset-4" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($address) ?>" target="_blank" rel="noopener">Petunjuk arah<span class="sr-only"> (tab baru)</span></a></dd></div></div>
                        <?php endif ?>
                        <?php if ($hours !== ''): ?>
                            <div class="lp-card flex gap-4 p-5"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-brand-sun"><?= sf_icon('clock') ?></span>
                                <div><dt class="text-xs font-bold uppercase tracking-[0.12em] text-ink-muted">Jam buka</dt><dd class="mt-1 font-semibold"><?= esc($hours) ?></dd></div></div>
                        <?php endif ?>
                        <?php if ($phone !== ''): ?>
                            <div class="lp-card flex gap-4 p-5"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-brand-sun"><?= sf_icon('phone') ?></span>
                                <div><dt class="text-xs font-bold uppercase tracking-[0.12em] text-ink-muted">Telepon / WhatsApp</dt><dd class="mt-1"><a class="inline-flex min-h-11 items-center font-semibold underline decoration-brand-sun decoration-2 underline-offset-4" href="tel:<?= esc($phoneDigits) ?>"><?= esc($phone) ?></a></dd></div></div>
                        <?php endif ?>
                    </dl>
                </div>
                <?php if ($address !== ''): ?>
                    <div class="reveal min-h-80 overflow-hidden rounded-[2rem] border border-border-warm bg-white shadow-lg">
                        <iframe class="h-full min-h-80 w-full" src="https://www.google.com/maps?q=<?= rawurlencode($address) ?>&amp;output=embed" title="Peta lokasi <?= esc($businessName) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                <?php endif ?>
            </div>
        </section>
        <?php endif ?>

        <!-- Ajakan akhir -->
        <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 sm:pb-24 lg:px-8">
            <div class="reveal relative overflow-hidden rounded-[2.5rem] bg-brand-chili px-6 py-12 text-center text-white sm:px-12 sm:py-16">
                <div class="pointer-events-none absolute -left-16 -top-16 h-56 w-56 rounded-full bg-brand-sun/30" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-20 -right-10 h-64 w-64 rounded-full bg-brand-cocoa/30" aria-hidden="true"></div>
                <h2 class="relative mx-auto max-w-2xl font-brand text-4xl font-extrabold leading-tight sm:text-5xl">Lagi pengen yang <span class="whitespace-nowrap">manis-manis?</span></h2>
                <p class="relative mx-auto mt-4 max-w-xl leading-7 text-white/90">Susun pesananmu sekarang, martabak langsung kami masak.</p>
                <div class="relative mt-8 flex flex-wrap justify-center gap-3">
                    <a class="lp-btn lp-btn-sun" href="#menu">Pilih menu sekarang</a>
                    <?php if ($waChat !== ''): ?><a class="lp-btn border-2 border-white text-white hover:bg-white hover:text-brand-chili" href="<?= esc($waChat) ?>" target="_blank" rel="noopener"><?= sf_wa_icon() ?>Tanya lewat WhatsApp<span class="sr-only"> (tab baru)</span></a><?php endif ?>
                </div>
            </div>
        </section>
    </main>

<?= $this->include('public/partials/footer') ?>

<?= $this->include('public/partials/order') ?>
</body>
</html>
