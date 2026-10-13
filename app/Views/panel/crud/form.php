<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<nav class="mb-6 text-sm" aria-label="Breadcrumb"><a class="font-bold underline decoration-brand-sun decoration-2 underline-offset-4" href="<?= site_url($config['index']) ?>">&larr; Kembali ke daftar</a></nav>
<div class="mb-8">
    <p class="section-kicker">Data baru</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Tambah <?= esc($config['label']) ?></h1>
    <?php if (!empty($config['intro'])): ?><p class="mt-3 max-w-2xl text-ink-muted"><?= esc($config['intro']) ?></p><?php endif ?>
</div>

<section class="panel-card max-w-3xl p-5 sm:p-7">
    <form action="<?= $action ?>" method="post" <?= !empty($config['multipart']) ? 'enctype="multipart/form-data"' : '' ?> class="grid gap-5 sm:grid-cols-2">
        <?= csrf_field() ?>
        <?= $this->include('panel/crud/_fields') ?>
        <?php if ($entity === 'banner'): ?><?= $this->include('panel/crud/_banner_preview') ?><?php endif ?>
        <div class="flex flex-wrap gap-3 sm:col-span-2">
            <button class="button-primary" type="submit">Simpan</button>
            <a class="button-secondary" href="<?= site_url($config['index']) ?>">Batal</a>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
