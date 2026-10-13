<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<div class="mb-8">
    <p class="section-kicker">Pengaturan akses</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Akun kasir</h1>
    <p class="mt-3 text-ink-muted">Buat akun, nonaktifkan akses, atau reset password kasir.</p>
</div>

<?php $errors = session()->getFlashdata('errors') ?? []; ?>
<section class="panel-card mb-8 p-5 sm:p-7">
    <h2 class="text-lg font-bold">Buat akun kasir</h2>
    <form action="<?= site_url('panel/users/create') ?>" method="post" class="mt-5 grid gap-4 sm:grid-cols-2">
        <?= csrf_field() ?>
        <div>
            <label class="mb-2 block text-sm font-semibold" for="name">Nama lengkap</label>
            <input class="form-input" id="name" name="name" value="<?= esc(old('name')) ?>" required>
            <?php if (isset($errors['name'])): ?><p class="form-error"><?= esc($errors['name']) ?></p><?php endif ?>
        </div>
        <div>
            <label class="mb-2 block text-sm font-semibold" for="username">Username</label>
            <input class="form-input" id="username" name="username" value="<?= esc(old('username')) ?>" autocomplete="username" required>
            <?php if (isset($errors['username'])): ?><p class="form-error"><?= esc($errors['username']) ?></p><?php endif ?>
        </div>
        <div>
            <label class="mb-2 block text-sm font-semibold" for="email">Email (opsional)</label>
            <input class="form-input" id="email" name="email" type="email" value="<?= esc(old('email')) ?>" autocomplete="email">
            <?php if (isset($errors['email'])): ?><p class="form-error"><?= esc($errors['email']) ?></p><?php endif ?>
        </div>
        <div>
            <label class="mb-2 block text-sm font-semibold" for="new-password">Password awal (minimal 12 karakter)</label>
            <input class="form-input" id="new-password" name="password" type="password" autocomplete="new-password" minlength="12" required>
            <?php if (isset($errors['password'])): ?><p class="form-error"><?= esc($errors['password']) ?></p><?php endif ?>
        </div>
        <div class="sm:col-span-2">
            <button class="button-primary" type="submit">Buat akun</button>
        </div>
    </form>
</section>

<section class="panel-card overflow-hidden">
    <div class="border-b border-stone-200 px-5 py-4"><h2 class="font-bold">Daftar kasir</h2></div>
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
                    <div class="flex flex-wrap gap-3">
                        <form action="<?= site_url('panel/users/status') ?>" method="post">
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
