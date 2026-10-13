<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$cell = static function (string $format, $value): string {
    if ($format === '@active') {
        return $value ? '<span class="status-label bg-[#F0FDF4] text-status-success">Aktif</span>' : '<span class="status-label bg-surface-soft-yellow text-ink-muted">Nonaktif</span>';
    }
    if ($format === '@status') {
        return $value === 'active' ? '<span class="status-label bg-[#F0FDF4] text-status-success">Aktif</span>' : '<span class="status-label bg-surface-soft-yellow text-ink-muted">Nonaktif</span>';
    }
    if ($format === '@type') {
        return esc($value === 'prepared' ? 'Dibuat dari resep' : 'Barang siap jual');
    }
    if (str_starts_with($format, '@yesno')) {
        return $value ? 'Ya' : '-';
    }
    if (str_starts_with($format, '@money')) {
        return 'Rp' . number_format((float) $value, 0, ',', '.');
    }

    return $value === null || $value === '' ? '-' : esc((string) $value);
};
?>
<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="section-kicker">Data master</p>
        <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Daftar <?= esc($config['plural']) ?></h1>
        <p class="mt-3 text-ink-muted"><?= count($rows) ?> data tersimpan.</p>
    </div>
    <a class="button-primary self-start" href="<?= site_url('panel/data/' . $entity . '/create') ?>">Tambah <?= esc($config['label']) ?></a>
</div>

<section class="panel-card overflow-hidden">
    <?php if ($rows === []): ?>
        <div class="p-8"><p class="font-display text-2xl">Belum ada data.</p><p class="mt-2 text-ink-muted">Pilih "Tambah <?= esc($config['label']) ?>" untuk memulai.</p></div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-left text-sm" data-datatable>
                <thead class="bg-surface-soft-yellow"><tr>
                    <th class="px-5 py-3 font-bold">No</th>
                    <?php foreach ($config['columns'] as $key => $label): ?>
                        <th class="px-5 py-3 font-bold"><?= esc(str_contains($label, ':') ? explode(':', $label, 2)[1] : (str_starts_with($label, '@') ? ucfirst(str_replace('_', ' ', $key === 'is_active' ? 'status' : $key)) : $label)) ?></th>
                    <?php endforeach ?>
                    <th class="px-5 py-3 text-right font-bold">Aksi</th>
                </tr></thead>
                <tbody class="divide-y divide-border-warm">
                    <?php foreach ($rows as $i => $row): ?>
                        <tr>
                            <td class="px-5 py-3 tabular-nums" data-dt-no><?= $i + 1 ?></td>
                            <?php foreach ($config['columns'] as $key => $format): ?>
                                <td class="px-5 py-3"><?= str_starts_with($format, '@') ? $cell($format, $row[$key] ?? null) : $cell('', $row[$key] ?? null) ?></td>
                            <?php endforeach ?>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-3">
                                    <?php foreach ($config['extraLinks'] ?? [] as $label => $url): ?><a class="text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-4" href="<?= site_url(str_replace('{id}', (string) $row['id'], $url)) ?>"><?= esc($label) ?></a><?php endforeach ?>
                                    <a class="button-secondary button-sm" href="<?= site_url('panel/data/' . $entity . '/' . (int) $row['id'] . '/edit') ?>">Edit</a>
                                    <?php if (isset($config['toggle'])): ?>
                                        <?php $isActive = array_key_exists('is_active', $row) ? (bool) $row['is_active'] : ($row['status'] ?? '') === 'active'; ?>
                                        <form action="<?= site_url($config['toggle'][0]) ?>" method="post" data-confirm-form="<?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?> <?= esc($config['toggle'][2]) ?> ini?">
                                            <?= csrf_field() ?><input type="hidden" name="<?= esc($config['toggle'][1]) ?>" value="<?= (int) $row['id'] ?>"><input type="hidden" name="_return" value="<?= esc($config['index']) ?>">
                                            <button class="button-secondary button-sm" type="submit"><?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                        </form>
                                    <?php endif ?>
                                    <?php if (isset($config['delete'])): ?>
                                        <form action="<?= site_url($config['delete'][0]) ?>" method="post" data-confirm-form="Hapus <?= esc($config['label']) ?> ini? Tindakan ini tidak dapat dibatalkan.">
                                            <?= csrf_field() ?><input type="hidden" name="<?= esc($config['delete'][1]) ?>" value="<?= (int) $row['id'] ?>"><input type="hidden" name="_return" value="<?= esc($config['index']) ?>">
                                            <button class="button-danger" type="submit">Hapus</button>
                                        </form>
                                    <?php endif ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
