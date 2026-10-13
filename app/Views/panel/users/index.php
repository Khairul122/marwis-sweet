<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<div class="mb-8">
    <p class="section-kicker">Pengaturan akses</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Akun kasir</h1>
    <p class="mt-3 text-ink-muted">Buat akun, nonaktifkan akses, atau reset password kasir.</p>
</div>

<section class="panel-card overflow-hidden">
    <div class="flex items-center justify-between gap-3 border-b border-border-warm px-5 py-4"><h2 class="font-bold">Daftar kasir</h2><a class="button-primary !min-h-9 !px-3" href="<?= site_url('panel/data/cashier/create') ?>">Tambah kasir</a></div>
    <?php if ($cashiers === []): ?>
        <p class="p-6 text-stone-600">Belum ada akun kasir.</p>
    <?php else: ?>
        <div class="divide-y divide-stone-100">
            <?php foreach ($cashiers as $cashier): ?>
                <article class="grid gap-5 p-5 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <p class="font-bold"><?= esc($cashier['name']) ?></p>
                        <p class="mt-1 text-sm text-stone-600"><?= esc($cashier['username']) ?><?= $cashier['email'] ? ' · ' . esc($cashier['email']) : '' ?></p>
                        <span class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-bold <?= $cashier['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-stone-100 text-stone-600' ?>"><?= $cashier['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?></span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <a class="inline-flex min-h-11 items-center text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-4" href="<?= site_url('panel/data/cashier/' . (int) $cashier['id'] . '/edit') ?>">Edit</a>
                        <form action="<?= site_url('panel/users/status') ?>" method="post" data-confirm-form="Ubah status akses akun kasir ini?">
                            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $cashier['id'] ?>">
                            <button class="button-secondary" type="submit"><?= $cashier['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                        </form>
                        <form action="<?= site_url('panel/users/reset-password') ?>" method="post" class="flex flex-wrap gap-2">
                            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $cashier['id'] ?>">
                            <label class="sr-only" for="password-<?= (int) $cashier['id'] ?>">Password baru untuk <?= esc($cashier['name']) ?></label>
                            <input class="form-input w-48" id="password-<?= (int) $cashier['id'] ?>" name="password" type="password" minlength="12" placeholder="Password baru" autocomplete="new-password" required>
                            <button class="button-primary" type="submit">Reset password</button>
                        </form>
                    </div>
                </article>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
