<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk | <?= esc(env('BUSINESS_NAME', 'Marwis Sweet')) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <script src="<?= base_url('assets/js/app.js') ?>" defer></script>
</head>
<body class="min-h-screen bg-surface-cream text-brand-cocoa antialiased">
    <a class="skip-link" href="#login-form">Lewati ke formulir masuk</a>
    <main class="grid min-h-screen lg:grid-cols-[.92fr_1.08fr]">
        <section class="hidden overflow-hidden border-r border-brand-cocoa bg-brand-sun p-10 lg:flex lg:flex-col lg:justify-between xl:p-14" aria-label="Marwis Sweet">
            <a class="flex items-center gap-3 self-start focus-ring" href="<?= site_url('/') ?>"><span class="label-cut grid h-12 w-12 place-items-center bg-brand-cocoa font-display text-xl text-brand-sun">M</span><span class="font-display text-xl"><?= esc(env('BUSINESS_NAME', 'Marwis Sweet')) ?></span></a>
            <div class="max-w-xl">
                <p class="text-sm font-bold uppercase tracking-[.12em]">Panel operasional</p>
                <h1 class="mt-5 font-display text-5xl leading-[1.03] xl:text-6xl">Kelola menu.<br>Jaga ritme usaha.</h1>
                <div class="receipt-rule my-8"></div>
                <p class="max-w-md text-lg leading-8">Akses untuk pemilik dan kasir yang telah terdaftar.</p>
            </div>
            <p class="text-sm font-semibold">Sistem informasi <?= esc(env('BUSINESS_NAME', 'Marwis Sweet')) ?></p>
        </section>
        <section class="flex items-center justify-center p-4 sm:p-8 lg:p-12">
            <div id="login-form" class="w-full max-w-md">
                <a class="mb-10 flex items-center gap-3 focus-ring lg:hidden" href="<?= site_url('/') ?>"><span class="label-cut grid h-11 w-11 place-items-center bg-brand-sun font-display text-lg">M</span><span class="font-display text-lg"><?= esc(env('BUSINESS_NAME', 'Marwis Sweet')) ?></span></a>
                <p class="section-kicker">Panel usaha</p>
                <h1 class="mt-3 font-display text-4xl">Masuk ke akun</h1>
                <p class="mt-3 text-ink-muted">Gunakan username atau email yang telah didaftarkan.</p>
                <?php if (session()->getFlashdata('success')): ?><div class="alert-success mt-6" role="status"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
                <?php if (session()->getFlashdata('error')): ?><div class="alert-danger mt-6" role="alert"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
                <?php $errors = session()->getFlashdata('errors') ?? []; ?>
                <form action="<?= site_url('login') ?>" method="post" class="mt-8 space-y-5">
                    <?= csrf_field() ?>
                    <div>
                        <label class="form-label" for="identity">Username atau email</label>
                        <input class="form-input" id="identity" name="identity" value="<?= esc(old('identity')) ?>" autocomplete="username" required aria-describedby="identity-error">
                        <?php if (isset($errors['identity'])): ?><p class="form-error" id="identity-error"><?= esc($errors['identity']) ?></p><?php endif ?>
                    </div>
                    <div>
                        <label class="form-label" for="password">Password</label>
                        <div class="relative"><input class="form-input pr-28" id="password" name="password" type="password" autocomplete="current-password" required aria-describedby="password-error"><button class="absolute inset-y-1 right-1 px-3 text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-2 focus-ring" type="button" data-password-toggle="password" aria-pressed="false">Tampilkan</button></div>
                        <?php if (isset($errors['password'])): ?><p class="form-error" id="password-error"><?= esc($errors['password']) ?></p><?php endif ?>
                    </div>
                    <button class="button-primary w-full" type="submit">Masuk ke panel</button>
                </form>
                <p class="mt-7 border-t border-border-warm pt-5 text-sm leading-6 text-ink-muted">Belum memiliki akses? Hubungi pemilik usaha. Pendaftaran publik tidak tersedia.</p>
            </div>
        </section>
    </main>
</body>
</html>
