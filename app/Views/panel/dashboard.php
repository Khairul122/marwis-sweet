<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<div class="mb-8">
    <p class="section-kicker"><?= esc($todayLabel) ?></p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Selamat datang, <?= esc($user['name'] ?? '') ?>.</h1>
    <p class="mt-3 max-w-2xl text-ink-muted">Fondasi akun sudah siap. Sekarang Anda dapat mulai menyusun katalog yang dilihat pelanggan.</p>
</div>

<?php if (($user['role'] ?? null) === 'owner'): ?>
    <section class="grid gap-0 overflow-hidden border border-brand-cocoa bg-surface-white lg:grid-cols-[1fr_auto]">
        <div class="p-6 sm:p-8">
            <p class="text-sm font-bold uppercase tracking-wider text-brand-chili">Tindakan berikutnya</p>
            <h2 class="mt-3 font-display text-2xl sm:text-3xl">Catat bahan yang masuk.</h2>
            <p class="mt-3 max-w-xl leading-7 text-ink-muted">Buat draft penerimaan, isi bahan dan biaya pembelian, lalu posting setelah seluruh data diperiksa.</p>
            <a class="button-primary mt-6" href="<?= site_url('panel/inventory') ?>">Buka penerimaan barang</a>
        </div>
        <div class="grid min-w-64 place-items-center border-t border-brand-cocoa bg-brand-sun p-8 lg:border-l lg:border-t-0" aria-hidden="true">
            <div class="rotate-[-3deg] border-2 border-brand-cocoa bg-surface-cream px-7 py-6 text-center"><span class="font-display text-5xl">03</span><span class="mt-1 block text-xs font-bold uppercase tracking-wider">Operasional</span></div>
        </div>
    </section>
<?php else: ?>
    <section class="border border-border-warm bg-surface-white p-6 sm:p-8">
        <h2 class="font-display text-2xl">Belum ada shift aktif.</h2>
        <p class="mt-2 text-ink-muted">Fitur shift dan POS akan tersedia pada tahap operasional.</p>
    </section>
<?php endif ?>

<section class="mt-8">
    <div class="mb-4"><h2 class="text-lg font-bold">Kemajuan aplikasi</h2><p class="mt-1 text-sm text-ink-muted">Fitur dibuka secara bertahap sesuai urutan PRD.</p></div>
    <ol class="divide-y divide-border-warm border-y border-border-warm bg-surface-white">
        <li class="flex items-center gap-4 px-5 py-4"><span class="stage-number is-complete">1</span><span><strong class="block">Fondasi</strong><span class="text-sm text-ink-muted">Akun, role, database, dan akses aplikasi</span></span><span class="ml-auto text-sm font-bold text-status-success">Selesai</span></li>
        <li class="flex items-center gap-4 px-5 py-4"><span class="stage-number is-complete">2</span><span><strong class="block">Master dan publik</strong><span class="text-sm text-ink-muted">Kategori, produk, varian, bahan, resep, dan katalog</span></span><span class="ml-auto text-sm font-bold text-status-success">Selesai</span></li>
        <li class="flex items-center gap-4 px-5 py-4"><span class="stage-number is-current">3</span><span><strong class="block">Operasional</strong><span class="text-sm text-ink-muted">Penerimaan dan kartu stok aktif</span></span><span class="ml-auto text-sm font-bold text-brand-chili">Berjalan</span></li>
        <?php foreach ([4 => 'Kontrol keuangan', 5 => 'Pelaporan dan ulasan', 6 => 'Stabilisasi'] as $number => $label): ?>
            <li class="flex items-center gap-4 px-5 py-4 text-ink-muted"><span class="stage-number"><?= $number ?></span><span class="font-semibold"><?= esc($label) ?></span></li>
        <?php endforeach ?>
    </ol>
</section>
<?= $this->endSection() ?>
