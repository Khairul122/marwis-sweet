<?php
/**
 * Halaman menu publik. Varian, harga, ulasan, dan foto berasal dari database; keranjang dibagi dengan landing.
 *
 * @var array $product
 * @var array $variants
 * @var array $images
 * @var array $reviewSummary
 * @var array $ratingCounts
 */
$total = (int) $reviewSummary['total'];
$average = number_format((float) $reviewSummary['average'], 1, ',', '.');
$gallery = $images !== [] ? $images : (!empty($product['image_path']) ? [['image_path' => $product['image_path'], 'alt_text' => $product['name']]] : []);
$canTopping = $toppings !== [] && in_array('recipe', array_column($variants, 'stock_mode'), true);
$waOrder = $waNumber !== '' ? 'https://wa.me/' . $waNumber . '?text=' . rawurlencode('Halo ' . $businessName . ', saya mau pesan ' . $product['name'] . '.') : '';
?><!doctype html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= esc($product['name']) ?> dari <?= esc($businessName) ?><?= $variants !== [] ? ', mulai ' . sf_rp(min(array_column($variants, 'price'))) : '' ?>. <?= esc(mb_strimwidth((string) $product['description'], 0, 120, '…')) ?>">
    <meta property="og:title" content="<?= esc($product['name']) ?> | <?= esc($businessName) ?>">
    <?php if ($gallery !== []): ?><meta property="og:image" content="<?= base_url($gallery[0]['image_path']) ?>"><?php endif ?>
    <title><?= esc($product['name']) ?> | <?= esc($businessName) ?></title>
<?= $this->include('public/partials/head') ?>
</head>
<body class="min-h-screen bg-white pb-20 font-jakarta text-brand-cocoa antialiased sm:pb-0">
    <a class="skip-link" href="#konten">Lewati ke konten</a>
<?= $this->include('public/partials/header') ?>

    <main id="konten">
        <!-- Produk -->
        <section class="relative overflow-hidden">
            <div class="pointer-events-none absolute -right-48 -top-48 h-[30rem] w-[30rem] rounded-full bg-brand-sun/30 blur-3xl" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-7xl px-4 pb-16 pt-6 sm:px-6 sm:pt-8 lg:px-8 lg:pb-20">
                <nav aria-label="Breadcrumb">
                    <ol class="flex flex-wrap items-center gap-1 text-sm font-semibold text-ink-muted">
                        <li><a class="inline-flex min-h-11 items-center hover:text-brand-cocoa hover:underline" href="<?= site_url('/') ?>">Beranda</a></li>
                        <li aria-hidden="true"><?= sf_icon('chevron-right', 'h-4 w-4') ?></li>
                        <li><a class="inline-flex min-h-11 items-center hover:text-brand-cocoa hover:underline" href="<?= base_url() ?>#menu">Menu</a></li>
                        <li aria-hidden="true"><?= sf_icon('chevron-right', 'h-4 w-4') ?></li>
                        <li class="text-brand-cocoa" aria-current="page"><?= esc($product['name']) ?></li>
                    </ol>
                </nav>

                <div class="mt-4 grid gap-10 lg:mt-6 lg:grid-cols-[1.05fr_.95fr] lg:gap-14">
                    <!-- Galeri -->
                    <div data-gallery>
                        <div class="relative">
                            <div class="absolute -inset-2.5 rotate-2 rounded-[2.25rem] bg-brand-sun" aria-hidden="true"></div>
                            <?php if ($gallery === []): ?>
                                <div class="relative grid aspect-[4/3] place-items-center rounded-[2rem] bg-surface-soft-yellow text-center ring-4 ring-white">
                                    <div><span class="text-ink-muted/50"><?= sf_icon('image', 'mx-auto h-16 w-16') ?></span><p class="mt-3 text-sm font-bold text-ink-muted">Foto produk belum tersedia</p></div>
                                </div>
                            <?php else: ?>
                                <img class="relative aspect-[4/3] w-full rounded-[2rem] object-cover shadow-2xl ring-4 ring-white" src="<?= base_url($gallery[0]['image_path']) ?>" alt="<?= esc($gallery[0]['alt_text'] ?: $product['name']) ?>" fetchpriority="high" data-gallery-main>
                            <?php endif ?>
                            <?php if (!empty($product['is_featured'])): ?><span class="absolute left-5 top-5 rounded-full bg-brand-chili px-4 py-1.5 text-xs font-extrabold uppercase tracking-wider text-white shadow-lg">Menu unggulan</span><?php endif ?>
                        </div>
                        <?php if (count($gallery) > 1): ?>
                            <div class="mt-6 flex gap-3 overflow-x-auto pb-1" role="group" aria-label="Pilih foto">
                                <?php foreach ($gallery as $i => $image): ?>
                                    <button class="shrink-0 overflow-hidden rounded-2xl ring-2 transition <?= $i === 0 ? 'ring-brand-cocoa' : 'ring-transparent opacity-70 hover:opacity-100' ?>" type="button" data-gallery-thumb data-src="<?= base_url($image['image_path']) ?>" data-alt="<?= esc($image['alt_text'] ?: $product['name']) ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
                                        <span class="sr-only">Foto <?= $i + 1 ?></span>
                                        <img class="h-20 w-24 object-cover" src="<?= base_url($image['image_path']) ?>" alt="" loading="lazy">
                                    </button>
                                <?php endforeach ?>
                            </div>
                        <?php endif ?>
                    </div>

                    <!-- Info + pesan -->
                    <div class="lg:sticky lg:top-24 lg:self-start" data-card data-product-id="<?= (int) $product['id'] ?>">
                        <p class="lp-eyebrow"><?= esc($product['category_name']) ?></p>
                        <h1 class="mt-4 font-brand text-4xl font-extrabold leading-[1.02] sm:text-5xl lg:text-6xl"><?= esc($product['name']) ?></h1>
                        <?php if ($total > 0): ?>
                            <a class="mt-3 inline-flex min-h-11 items-center gap-2 text-sm" href="#ulasan-produk"><span class="text-base text-brand-sun-hover" aria-hidden="true"><?= sf_stars((int) round((float) $reviewSummary['average'])) ?></span><strong><?= $average ?></strong><span class="text-ink-muted underline decoration-brand-sun decoration-2 underline-offset-4"><?= $total ?> ulasan</span></a>
                        <?php endif ?>
                        <?php if ($product['description']): ?><p class="mt-4 max-w-xl text-lg leading-8 text-ink-muted"><?= nl2br(esc($product['description'])) ?></p><?php endif ?>

                        <div class="mt-7 rounded-3xl border border-border-warm bg-white p-5 shadow-[0_18px_40px_-24px_rgb(45_27_18/.35)] sm:p-6">
                            <?php if ($variants === []): ?>
                                <p class="font-semibold text-ink-muted">Harga belum tersedia. Silakan tanyakan langsung ke kami.</p>
                            <?php else: ?>
                                <p class="text-sm font-extrabold">Pilih <?= count($variants) > 1 ? 'ukuran / varian' : 'varian' ?></p>
                                <div class="mt-3"><?= sf_variant_picker($product, $variants, 'detail', true) ?></div>
                                <div class="mt-6 flex flex-wrap items-end justify-between gap-4 border-t border-dashed border-border-warm pt-5">
                                    <div><p class="text-xs font-bold uppercase tracking-[0.12em] text-ink-muted">Harga</p><p class="font-brand text-4xl font-extrabold tabular-nums" data-price-out><?= sf_rp($variants[0]['price']) ?></p></div>
                                    <?php if ($canTopping): ?><p class="text-sm text-ink-muted">+ topping bisa dipilih di keranjang</p><?php endif ?>
                                </div>
                                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                    <button class="lp-btn lp-btn-sun" type="button" data-add><?= sf_icon('plus', 'h-5 w-5') ?>Tambah ke pesanan</button>
                                    <?php if ($waOrder !== ''): ?><a class="lp-btn lp-btn-dark" href="<?= esc($waOrder) ?>" target="_blank" rel="noopener"><?= sf_wa_icon() ?>Tanya via WhatsApp<span class="sr-only"> (tab baru)</span></a><?php endif ?>
                                </div>
                            <?php endif ?>
                        </div>

                        <ul class="mt-6 grid gap-3 text-sm sm:grid-cols-2">
                            <li class="flex items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-sun/40 text-brand-cocoa"><?= sf_icon('flame', 'h-5 w-5') ?></span><span class="font-semibold">Dimasak setelah dipesan</span></li>
                            <?php if ($hours !== ''): ?><li class="flex items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-sun/40 text-brand-cocoa"><?= sf_icon('clock', 'h-5 w-5') ?></span><span class="font-semibold">Buka <?= esc($hours) ?></span></li><?php endif ?>
                            <?php if ($address !== ''): ?><li class="flex items-center gap-3 sm:col-span-2"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-sun/40 text-brand-cocoa"><?= sf_icon('map-pin', 'h-5 w-5') ?></span><span class="font-semibold"><?= esc($address) ?></span></li><?php endif ?>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($canTopping): ?>
        <!-- Topping -->
        <section class="bg-brand-sun py-14 sm:py-16" aria-labelledby="topping-title">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[.6fr_1.4fr] lg:items-center lg:px-8">
                <div>
                    <h2 id="topping-title" class="font-brand text-3xl font-extrabold leading-tight sm:text-4xl">Bikin makin penuh</h2>
                    <p class="mt-2 leading-7">Tambah topping per porsi lewat keranjang setelah menekan <strong>Tambah ke pesanan</strong>.</p>
                </div>
                <?= sf_topping_cards($toppings, 'grid-cols-2 sm:grid-cols-4') ?>
            </div>
        </section>
        <?php endif ?>

        <!-- Ulasan produk: hanya yang sudah disetujui moderator -->
        <section id="ulasan-produk" class="scroll-mt-24 py-16 sm:py-20" aria-labelledby="ulasan-title">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[20rem_1fr] lg:px-8">
                <div class="lg:sticky lg:top-24 lg:self-start">
                    <p class="lp-eyebrow">Kata pembeli</p>
                    <h2 id="ulasan-title" class="lp-title mt-4">Ulasan pembeli</h2>
                    <?php if ($total > 0): ?>
                        <div class="mt-6 flex items-center gap-4">
                            <p class="font-brand text-6xl font-extrabold leading-none tabular-nums"><?= $average ?></p>
                            <div><p class="text-lg text-brand-sun-hover" aria-hidden="true"><?= sf_stars((int) round((float) $reviewSummary['average'])) ?></p><p class="text-sm text-ink-muted">dari <?= $total ?> ulasan</p></div>
                        </div>
                        <dl class="mt-6 space-y-2">
                            <?php foreach ($ratingCounts as $star => $count): ?>
                                <div class="flex items-center gap-3 text-sm">
                                    <dt class="w-10 shrink-0 font-bold tabular-nums"><?= $star ?> <span class="text-brand-sun-hover" aria-hidden="true">★</span><span class="sr-only">bintang</span></dt>
                                    <dd class="h-2.5 flex-1 overflow-hidden rounded-full bg-surface-soft-yellow"><span class="block h-full rounded-full bg-brand-sun" style="width: <?= round($count / $total * 100) ?>%"></span></dd>
                                    <dd class="w-6 shrink-0 text-right tabular-nums text-ink-muted"><?= $count ?></dd>
                                </div>
                            <?php endforeach ?>
                        </dl>
                    <?php endif ?>
                    <p class="mt-6 text-sm leading-6 text-ink-muted">Ulasan ditulis pembeli lewat tautan di struk dan diperiksa sebelum tampil.</p>
                </div>

                <div>
                    <?php if ($reviews === []): ?>
                        <div class="lp-card p-8 text-center sm:p-10">
                            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-sun/40"><?= sf_icon('message-square') ?></span>
                            <p class="mt-4 font-brand text-2xl font-bold">Belum ada ulasan untuk menu ini</p>
                            <p class="mx-auto mt-2 max-w-md text-ink-muted">Sudah pernah coba? Tulis ulasan lewat tautan pada struk pembelianmu.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <?php foreach ($reviews as $review): ?>
                                <?php $who = $review['display_name'] ?: 'Pembeli'; ?>
                                <figure class="lp-card flex flex-col overflow-hidden">
                                    <div class="flex flex-1 flex-col p-6">
                                        <p class="text-brand-sun-hover" role="img" aria-label="<?= (int) $review['rating'] ?> dari 5 bintang"><?= sf_stars((int) $review['rating']) ?></p>
                                        <blockquote class="mt-3 flex-1 leading-7">“<?= esc($review['comment']) ?>”</blockquote>
                                        <figcaption class="mt-5 flex items-center gap-3 border-t border-border-warm pt-4 text-sm">
                                            <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-sun font-bold" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($who, 0, 1))) ?></span>
                                            <span><strong class="block"><?= esc($who) ?></strong><time class="text-ink-muted" datetime="<?= esc(date('Y-m-d', strtotime($review['created_at']))) ?>"><?= esc(date('d/m/Y', strtotime($review['created_at']))) ?></time></span>
                                        </figcaption>
                                    </div>
                                    <?php if (!empty($reviewImages[$review['id']])): ?>
                                        <div class="grid grid-cols-3 gap-1 p-1 pt-0">
                                            <?php foreach ($reviewImages[$review['id']] as $image): ?>
                                                <a class="overflow-hidden rounded-xl" href="<?= site_url('ulasan/foto/' . $image['id']) ?>" target="_blank" rel="noopener"><img class="aspect-square w-full object-cover transition hover:scale-105" src="<?= site_url('ulasan/foto/' . $image['id']) ?>" alt="Foto ulasan <?= esc($product['name']) ?> dari <?= esc($who) ?>" loading="lazy"><span class="sr-only"> (tab baru)</span></a>
                                            <?php endforeach ?>
                                        </div>
                                    <?php endif ?>
                                </figure>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        </section>

        <?php if ($related !== []): ?>
        <!-- Menu lainnya -->
        <section class="border-t border-border-warm py-16 sm:py-20" aria-labelledby="lainnya-title">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="lp-eyebrow">Menu lainnya</p>
                        <h2 id="lainnya-title" class="lp-title mt-4">Sekalian coba yang ini</h2>
                    </div>
                    <a class="lp-btn lp-btn-light" href="<?= base_url() ?>#menu">Lihat semua menu<?= sf_icon('arrow-right', 'h-5 w-5') ?></a>
                </div>
                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <?php foreach ($related as $item): ?>
                        <?php $itemVariants = $variantsByProduct[$item['id']] ?? []; $itemRating = $ratings[$item['id']] ?? null; ?>
                        <a class="lp-card group flex flex-col overflow-hidden transition duration-200 hover:-translate-y-1 hover:shadow-[0_24px_50px_-24px_rgb(45_27_18/.5)]" href="<?= site_url('menu/' . $item['slug']) ?>">
                            <span class="relative grid aspect-[4/3] place-items-center overflow-hidden bg-surface-soft-yellow">
                                <?php if (!empty($item['image_path'])): ?>
                                    <img class="h-full w-full object-cover transition duration-500 group-hover:scale-105" src="<?= base_url($item['image_path']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="font-brand text-6xl font-extrabold text-brand-sun-hover/40" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($item['name'], 0, 1))) ?></span>
                                <?php endif ?>
                                <span class="absolute left-3 top-3 rounded-full bg-white/95 px-3 py-1 text-xs font-bold shadow-sm"><?= esc($item['category_name']) ?></span>
                            </span>
                            <span class="flex flex-1 flex-col p-5">
                                <span class="font-brand text-xl font-bold leading-tight group-hover:underline"><?= esc($item['name']) ?></span>
                                <?php if ($itemRating): ?><span class="mt-1 text-sm text-ink-muted"><span class="text-brand-sun-hover" aria-hidden="true">★</span> <strong class="text-brand-cocoa"><?= number_format($itemRating['average'], 1, ',', '.') ?></strong> · <?= $itemRating['total'] ?> ulasan</span><?php endif ?>
                                <span class="mt-auto pt-4 text-sm text-ink-muted"><?= $itemVariants !== [] ? 'Mulai <strong class="font-brand text-xl text-brand-cocoa tabular-nums">' . sf_rp(min(array_column($itemVariants, 'price'))) . '</strong>' : 'Harga belum tersedia' ?></span>
                            </span>
                        </a>
                    <?php endforeach ?>
                </div>
            </div>
        </section>
        <?php endif ?>
    </main>

<?= $this->include('public/partials/footer') ?>

<?= $this->include('public/partials/order') ?>
</body>
</html>
