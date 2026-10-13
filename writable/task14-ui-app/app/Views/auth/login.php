<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk | <?= esc(env('BUSINESS_NAME', 'Marwis Sweet')) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <script src="<?= base_url('assets/js/app.js') ?>" defer></script>
</head>
<body class="min-h-screen bg-white text-brand-cocoa antialiased">
    <a class="skip-link" href="#login-form">Lewati ke formulir masuk</a>
    <main class="grid min-h-screen place-items-center px-4 py-8 sm:px-6">
        <section class="w-full max-w-md" aria-labelledby="login-title">
            <a class="mb-7 inline-flex min-h-11 items-center gap-3 rounded-sm focus-ring" href="<?= site_url('/') ?>" aria-label="<?= esc(env('BUSINESS_NAME', 'Marwis Sweet')) ?>, kembali ke menu">
                <span class="label-cut grid h-11 w-11 place-items-center bg-brand-sun font-display text-lg text-brand-cocoa" aria-hidden="true">M</span>
                <span class="font-display text-lg"><?= esc(env('BUSINESS_NAME', 'Marwis Sweet')) ?></span>
            </a>

            <div class="panel-card p-5 sm:p-8">
                <p class="section-kicker">Panel usaha</p>
                <h1 class="mt-2 font-display text-3xl" id="login-title">Masuk ke akun</h1>
                <p class="mt-2 text-sm leading-6 text-ink-muted">Gunakan username atau email yang telah didaftarkan.</p>

                <?php if (session()->getFlashdata('success')): ?><div class="alert-success mt-5" role="status"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
                <?php if (session()->getFlashdata('error')): ?><div class="alert-danger mt-5" role="alert"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
                <?php $errors = session()->getFlashdata('errors') ?? []; ?>

                <form id="login-form" action="<?= site_url('login') ?>" method="post" class="mt-6 space-y-5">
                    <?= csrf_field() ?>
                    <div>
                        <label class="form-label" for="identity">Username atau email</label>
                        <input class="form-input" id="identity" name="identity" value="<?= esc(old('identity')) ?>" autocomplete="username" required <?= isset($errors['identity']) ? 'aria-describedby="identity-error"' : '' ?>>
                        <?php if (isset($errors['identity'])): ?><p class="form-error" id="identity-error"><?= esc($errors['identity']) ?></p><?php endif ?>
                    </div>
                    <div>
                        <label class="form-label" for="password">Password</label>
                        <div class="relative">
                            <input class="form-input pr-28" id="password" name="password" type="password" autocomplete="current-password" required <?= isset($errors['password']) ? 'aria-describedby="password-error"' : '' ?>>
                            <button class="absolute inset-y-1 right-1 min-h-10 rounded-sm px-3 text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-2 focus-ring" type="button" data-password-toggle="password" aria-pressed="false">Tampilkan</button>
                        </div>
                        <?php if (isset($errors['password'])): ?><p class="form-error" id="password-error"><?= esc($errors['password']) ?></p><?php endif ?>
                    </div>
                    <button class="button-primary w-full" type="submit">Masuk ke panel</button>
                </form>
            </div>

            <p class="mt-5 text-center text-sm leading-6 text-ink-muted">Belum memiliki akses? Hubungi pemilik usaha.</p>
            <a class="mx-auto mt-2 flex min-h-11 w-fit items-center px-3 text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-4 focus-ring" href="<?= site_url('/') ?>">Kembali ke menu</a>
        </section>
    </main>
</body>
</html>
