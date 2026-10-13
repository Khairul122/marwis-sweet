<?php $home = base_url(); ?>
    <footer class="bg-brand-cocoa text-[#E8D9BD]">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr] lg:px-8">
            <div>
                <p class="flex items-center gap-2.5"><span class="grid h-10 w-10 place-items-center rounded-full bg-brand-sun font-brand text-xl font-extrabold text-brand-cocoa" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($businessName, 0, 1))) ?></span><strong class="font-brand text-2xl font-extrabold text-white"><?= esc($businessName) ?></strong></p>
                <p class="mt-4 max-w-sm text-sm leading-6">Martabak manis dan telur, dimasak setelah dipesan. Harga jelas, topping bebas pilih.</p>
            </div>
            <nav aria-label="Tautan footer">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-brand-sun">Jelajahi</p>
                <ul class="mt-3 space-y-1 text-sm font-semibold">
                    <li><a class="inline-flex min-h-11 items-center hover:text-white" href="<?= $home ?>#menu">Menu &amp; harga</a></li>
                    <li><a class="inline-flex min-h-11 items-center hover:text-white" href="<?= $home ?>#cara-pesan">Cara pesan</a></li>
                    <?php if ($hasContact): ?><li><a class="inline-flex min-h-11 items-center hover:text-white" href="<?= $home ?>#lokasi">Lokasi</a></li><?php endif ?>
                </ul>
            </nav>
            <?php if ($hasContact): ?>
                <div class="text-sm leading-6">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-brand-sun">Kontak</p>
                    <?php if ($address !== ''): ?><p class="mt-3"><?= esc($address) ?></p><?php endif ?>
                    <?php if ($hours !== ''): ?><p class="mt-2"><?= esc($hours) ?></p><?php endif ?>
                    <?php if ($phone !== ''): ?><p class="mt-2"><a class="font-semibold text-white underline-offset-4 hover:underline" href="tel:<?= esc($phoneDigits) ?>"><?= esc($phone) ?></a></p><?php endif ?>
                </div>
            <?php endif ?>
        </div>
        <div class="border-t border-white/10">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <p>&copy; <?= date('Y') ?> <?= esc($businessName) ?></p>
                <a class="inline-flex min-h-11 items-center font-semibold underline decoration-brand-sun decoration-2 underline-offset-4 hover:text-white" href="<?= site_url('login') ?>">Masuk untuk pegawai</a>
            </div>
        </div>
    </footer>
