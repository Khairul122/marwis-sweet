<?php
/**
 * Header publik. Tautan bagian memakai URL beranda penuh agar juga jalan dari halaman menu;
 * di beranda sendiri browser hanya menggulir ke bagiannya.
 */
$home = base_url();
$navLinks = array_filter([
    ['#menu', 'Menu'],
    $toppings !== [] ? ['#topping', 'Topping'] : null,
    ['#cara-pesan', 'Cara pesan'],
    (int) ($storeReviews['total'] ?? 0) > 0 ? ['#ulasan', 'Ulasan'] : null,
    $hasContact ? ['#lokasi', 'Lokasi'] : null,
]);
?>

    <header class="sticky top-0 z-40 border-b border-border-warm bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a class="inline-flex min-h-11 items-center gap-2.5 rounded-full" href="<?= site_url('/') ?>" aria-label="<?= esc($businessName) ?>, halaman utama">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-sun font-brand text-xl font-extrabold leading-none text-brand-cocoa ring-2 ring-brand-cocoa" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($businessName, 0, 1))) ?></span>
                <span class="font-brand text-xl font-extrabold leading-none sm:text-2xl"><?= esc($businessName) ?></span>
            </a>
            <nav class="hidden items-center gap-1 md:flex" aria-label="Navigasi utama">
                <?php foreach ($navLinks as [$hash, $label]): ?><a class="lp-nav" href="<?= $home . $hash ?>"><?= $label ?></a><?php endforeach ?>
            </nav>
            <div class="flex items-center gap-2">
                <button class="lp-btn lp-btn-dark !min-h-11 !px-4" type="button" data-order-open><?= sf_icon('shopping-bag', 'h-5 w-5') ?><span class="hidden sm:inline">Pesanan</span> <span class="grid h-6 min-w-6 place-items-center rounded-full bg-brand-sun px-1.5 text-xs text-brand-cocoa tabular-nums" data-order-count>0</span></button>
                <button class="grid h-11 w-11 place-items-center rounded-full border border-border-warm bg-white md:hidden" type="button" data-menu-toggle aria-expanded="false" aria-controls="menu-mobile">
                    <span class="sr-only">Buka menu</span>
                    <?= sf_icon('menu') ?>
                </button>
            </div>
        </div>
        <nav id="menu-mobile" class="hidden border-t border-border-warm px-4 py-3 md:hidden" data-menu-mobile aria-label="Navigasi mobile">
            <?php foreach ($navLinks as [$hash, $label]): ?><a class="lp-nav block" href="<?= $home . $hash ?>"><?= $label ?></a><?php endforeach ?>
        </nav>
    </header>
