<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= esc($product['name']) ?> dari <?= esc($businessName) ?>.">
    <title><?= esc($product['name']) ?> | <?= esc($businessName) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body class="min-h-screen bg-surface-cream text-brand-cocoa antialiased">
    <a class="skip-link" href="#konten">Lewati ke konten</a>
    <header class="border-b border-brand-cocoa bg-brand-sun">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a class="flex items-center gap-3 focus-ring" href="<?= site_url('/') ?>"><span class="label-cut inline-grid h-11 w-11 place-items-center bg-brand-cocoa font-display text-lg text-brand-sun" aria-hidden="true">M</span><span class="font-display text-lg"><?= esc($businessName) ?></span></a>
            <a class="button-primary-inverse" href="<?= site_url('/#menu') ?>">Kembali ke menu</a>
        </div>
    </header>
    <main id="konten" class="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-16 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[1.05fr_.95fr] lg:items-start">
            <section aria-label="Foto <?= esc($product['name']) ?>">
                <?php if ($images === []): ?>
                    <div class="grid aspect-[4/3] place-items-center border-2 border-brand-cocoa bg-surface-soft-yellow"><div class="text-center"><span class="font-display text-8xl opacity-20" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($product['name'], 0, 1))) ?></span><p class="mt-2 text-sm font-bold">Foto produk belum tersedia</p></div></div>
                <?php else: ?>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <?php foreach ($images as $index => $image): ?><img class="w-full border-2 border-brand-cocoa object-cover <?= $index === 0 ? 'aspect-[4/3] sm:col-span-2' : 'aspect-square' ?>" src="<?= base_url($image['image_path']) ?>" alt="<?= esc($image['alt_text']) ?>"><?php endforeach ?>
                    </div>
                <?php endif ?>
            </section>
            <section class="lg:sticky lg:top-8">
                <p class="section-kicker"><?= esc($product['category_name']) ?></p>
                <h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl"><?= esc($product['name']) ?></h1>
                <?php if ((int) $reviewSummary['total'] > 0): ?><p class="mt-3 text-sm font-bold text-brand-chili"><span aria-hidden="true">★</span> <?= number_format((float) $reviewSummary['average'], 1, ',', '.') ?> dari 5 · <?= (int) $reviewSummary['total'] ?> ulasan</p><?php endif ?>
                <?php if ($product['description']): ?><p class="mt-5 text-lg leading-8 text-ink-muted"><?= nl2br(esc($product['description'])) ?></p><?php endif ?>
                <div class="receipt-rule my-7"></div>
                <h2 class="text-sm font-bold uppercase tracking-wider">Pilihan dan harga</h2>
                <?php if ($variants === []): ?><p class="mt-4 text-ink-muted">Harga belum tersedia.</p><?php else: ?><ul class="mt-4 divide-y divide-border-warm border-y border-border-warm"><?php foreach ($variants as $variant): ?><li class="flex items-baseline justify-between gap-5 py-4"><span class="font-semibold"><?= esc($variant['name']) ?></span><strong class="text-lg tabular-nums">Rp<?= number_format((float) $variant['price'], 0, ',', '.') ?></strong></li><?php endforeach ?></ul><?php endif ?>
                <div class="mt-8 border border-brand-cocoa bg-brand-sun p-5"><h2 class="font-display text-xl">Pesan di outlet</h2><p class="mt-2 text-sm leading-6">Checkout online belum tersedia. Silakan beli langsung di outlet<?= !empty($settings['phone']) ? ' atau hubungi kontak usaha' : '' ?>.</p><?php if (!empty($settings['phone'])): ?><a class="button-primary-inverse mt-4" href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $settings['phone'])) ?>">Hubungi <?= esc($settings['phone']) ?></a><?php endif ?></div>
            </section>
        </div>
        <section class="mt-16 border-t border-brand-cocoa pt-10" aria-labelledby="product-reviews"><div class="grid gap-6 lg:grid-cols-[.7fr_1.3fr]"><div><p class="section-kicker">Cerita pelanggan</p><h2 id="product-reviews" class="mt-2 font-display text-3xl">Ulasan yang sudah diperiksa</h2><?php if ((int) $reviewSummary['total'] > 0): ?><p class="mt-4 text-lg"><strong><?= number_format((float) $reviewSummary['average'], 1, ',', '.') ?></strong> dari 5 berdasarkan <?= (int) $reviewSummary['total'] ?> ulasan.</p><?php endif ?></div><div><?php if ($reviews === []): ?><div class="panel-card p-6"><p class="font-semibold">Belum ada ulasan yang diterbitkan.</p><p class="mt-2 text-sm text-ink-muted">Pelanggan dapat mengulas melalui tautan pada struk pembelian.</p></div><?php else: ?><div class="grid gap-4 sm:grid-cols-2"><?php foreach ($reviews as $review): ?><article class="panel-card overflow-hidden"><div class="p-5"><p class="text-brand-chili" aria-label="Rating <?= (int) $review['rating'] ?> dari 5"><?= str_repeat('★', (int) $review['rating']) . str_repeat('☆', 5 - (int) $review['rating']) ?></p><blockquote class="mt-3 leading-7">“<?= esc($review['comment']) ?>”</blockquote><p class="mt-4 text-sm font-bold"><?= esc($review['display_name'] ?: 'Anonim') ?></p><p class="text-xs text-ink-muted"><?= esc(date('d/m/Y', strtotime($review['created_at']))) ?></p></div><?php if (!empty($reviewImages[$review['id']])): ?><div class="grid grid-cols-3 gap-px border-t border-border-warm bg-border-warm"><?php foreach ($reviewImages[$review['id']] as $image): ?><a href="<?= site_url('ulasan/foto/' . $image['id']) ?>" target="_blank" rel="noopener"><img class="aspect-square w-full object-cover" src="<?= site_url('ulasan/foto/' . $image['id']) ?>" alt="Foto ulasan <?= esc($product['name']) ?>" loading="lazy"></a><?php endforeach ?></div><?php endif ?></article><?php endforeach ?></div><?php endif ?></div></div></section>
    </main>
</body>
</html>
