<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php $isEdit = $mode === 'edit'; ?>
<nav class="mb-6 text-sm" aria-label="Breadcrumb"><a class="font-bold underline decoration-brand-sun decoration-2 underline-offset-4" href="<?= site_url($config['back']) ?>">&larr; Kembali ke daftar</a></nav>
<div class="mb-8">
    <p class="section-kicker"><?= $isEdit ? 'Edit data' : 'Data baru' ?></p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl"><?= $isEdit ? 'Edit' : 'Tambah' ?> <?= esc($config['label']) ?></h1>
    <?php if (!empty($config['intro'])): ?><p class="mt-3 max-w-2xl text-ink-muted"><?= esc($config['intro']) ?></p><?php endif ?>
</div>

<section class="panel-card max-w-3xl p-5 sm:p-7">
    <form action="<?= $action ?>" method="post" <?= !empty($config['multipart']) ? 'enctype="multipart/form-data"' : '' ?> class="grid gap-5 sm:grid-cols-2" data-form-page>
        <?= csrf_field() ?>
        <?php foreach ($hidden as $name => $value): ?><input type="hidden" name="<?= esc($name) ?>" value="<?= (int) $value ?>"><?php endforeach ?>
        <?php foreach ($fields as $field): ?>
            <?php
            $name = $field['name'];
            $type = $field['type'] ?? 'text';
            $id = 'f-' . $name;
            $value = old($name, $isEdit ? ($row[$field['col'] ?? $name] ?? '') : ($field['default'] ?? ''));
            $wide = !empty($field['wide']) || $type === 'textarea';
            $label = $field['label'] . (empty($field['required']) && $type !== 'checkbox' ? ' <span class="font-normal text-ink-muted">(opsional)</span>' : '');
            $common = 'id="' . $id . '" name="' . esc($name) . '"' . (!empty($field['required']) ? ' required' : '') . (isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="' . $id . '-error"' : '');
            ?>
            <?php if ($type === 'checkbox'): ?>
                <label class="flex min-h-12 items-center gap-3 border border-border-warm bg-surface-soft-yellow px-4 text-sm font-semibold <?= $wide ? 'sm:col-span-2' : '' ?>"><input class="h-5 w-5 accent-brand-cocoa" type="checkbox" name="<?= esc($name) ?>" value="1" <?= old($name, $isEdit ? ($row[$name] ?? '') : '') ? 'checked' : '' ?>> <?= esc($field['label']) ?></label>
            <?php else: ?>
                <div class="<?= $wide ? 'sm:col-span-2' : '' ?>">
                    <label class="form-label" for="<?= $id ?>"><?= $label ?></label>
                    <?php if ($type === 'textarea'): ?>
                        <textarea class="form-input min-h-24 resize-y" <?= $common ?> <?= isset($field['max']) ? 'maxlength="' . (int) $field['max'] . '"' : '' ?>><?= esc($value) ?></textarea>
                    <?php elseif ($type === 'select'): ?>
                        <select class="form-input" <?= $common ?>>
                            <?php if (isset($field['blank'])): ?><option value=""><?= esc($field['blank']) ?></option><?php endif ?>
                            <?php foreach ($field['options'] as $optValue => $optLabel): ?><option value="<?= esc($optValue) ?>" <?= (string) $value === (string) $optValue ? 'selected' : '' ?>><?= esc($optLabel) ?></option><?php endforeach ?>
                        </select>
                    <?php elseif ($type === 'money'): ?>
                        <div class="input-prefix"><span>Rp</span><input <?= $common ?> type="number" min="0" step="1" value="<?= esc($value !== '' ? (string) (int) $value : '') ?>"></div>
                    <?php elseif ($type === 'file'): ?>
                        <input class="form-input file:mr-3 file:border-0 file:bg-brand-sun file:px-3 file:py-1 file:font-bold" <?= $common ?> type="file" accept="image/jpeg,image/png,image/webp" data-image-input>
                    <?php else: ?>
                        <input class="form-input <?= !empty($field['upper']) ? 'uppercase' : '' ?>" <?= $common ?> type="<?= esc($type) ?>" value="<?= $type === 'password' ? '' : esc($value) ?>"
                            <?= isset($field['max']) ? 'maxlength="' . (int) $field['max'] . '"' : '' ?> <?= isset($field['min']) && $type !== 'number' ? 'minlength="' . (int) $field['min'] . '"' : '' ?>
                            <?= $type === 'number' ? 'min="0" step="' . esc($field['step'] ?? '1') . '"' : '' ?>
                            <?= isset($field['placeholder']) ? 'placeholder="' . esc($field['placeholder']) . '"' : '' ?> <?= isset($field['autocomplete']) ? 'autocomplete="' . esc($field['autocomplete']) . '"' : '' ?>>
                    <?php endif ?>
                    <?php if (!empty($field['hint'])): ?><p class="mt-1.5 text-sm text-ink-muted"><?= esc($field['hint']) ?></p><?php endif ?>
                    <?php if (isset($errors[$name])): ?><p class="form-error" id="<?= $id ?>-error"><?= esc($errors[$name]) ?></p><?php endif ?>
                </div>
            <?php endif ?>
        <?php endforeach ?>
        <div class="flex flex-wrap gap-3 sm:col-span-2">
            <button class="button-primary" type="submit"><?= $isEdit ? 'Simpan perubahan' : 'Simpan' ?></button>
            <a class="button-secondary" href="<?= site_url($config['back']) ?>">Batal</a>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
