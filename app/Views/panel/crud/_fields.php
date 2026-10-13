<?php foreach ($hidden as $name => $value): ?><input type="hidden" name="<?= esc($name) ?>" value="<?= (int) $value ?>"><?php endforeach ?>
<?php if (empty($config['noReturn'])): ?><input type="hidden" name="_return" value="<?= esc($config['index']) ?>"><?php endif ?>
<?php foreach ($fields as $field): ?>
    <?php
    $name = $field['name'];
    $type = $field['type'] ?? 'text';
    $id = 'f-' . $name;
    $value = old($name, $mode === 'edit' ? ($row[$field['col'] ?? $name] ?? '') : ($field['default'] ?? ''));
    $wide = !empty($field['wide']) || $type === 'textarea';
    $label = $field['label'] . (empty($field['required']) && $type !== 'checkbox' ? ' <span class="font-normal text-ink-muted">(opsional)</span>' : '');
    $common = 'id="' . $id . '" name="' . esc($name) . '"' . (!empty($field['required']) ? ' required' : '') . (isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="' . $id . '-error"' : '');
    ?>
    <?php if ($type === 'checkbox'): ?>
        <label class="flex min-h-12 items-center gap-3 border border-border-warm bg-surface-soft-yellow px-4 text-sm font-semibold <?= $wide ? 'sm:col-span-2' : '' ?>"><input class="h-5 w-5 accent-brand-cocoa" type="checkbox" name="<?= esc($name) ?>" value="1" <?= old($name, $mode === 'edit' ? ($row[$name] ?? '') : '') ? 'checked' : '' ?>> <?= esc($field['label']) ?></label>
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
                <div class="input-prefix"><span>Rp</span><input <?= $common ?> type="number" min="<?= esc($field['minv'] ?? 0) ?>" step="<?= esc($field['step'] ?? '1') ?>" value="<?= esc(($field['step'] ?? '') === '0.01' ? (string) $value : ($value !== '' ? (string) (int) $value : '')) ?>"></div>
            <?php elseif ($type === 'file'): ?>
                <input class="form-input file:mr-3 file:border-0 file:bg-brand-sun file:px-3 file:py-1 file:font-bold" <?= $common ?> type="file" accept="<?= esc($field['accept'] ?? 'image/jpeg,image/png,image/webp') ?>" <?= $entity === 'banner' && $name === 'image' ? 'data-image-preview-input' : '' ?>>
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
