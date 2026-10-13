<?php if ($formItems !== []): ?>
<section class="panel-card -mt-4 mb-8 max-w-3xl p-5 sm:p-6">
    <form action="<?= site_url($formUrl) ?>" method="post" class="grid gap-4 sm:grid-cols-[1fr_9rem_auto] sm:items-end">
        <?= csrf_field() ?><input type="hidden" name="<?= esc($formField) ?>" value="<?= (int) $formId ?>"><input type="hidden" name="_return" value="<?= esc($returnUrl) ?>">
        <div><label class="form-label" for="line-item">Bahan</label><select class="form-input" id="line-item" name="item_id" required><option value="">Pilih bahan</option><?php foreach ($formItems as $item): ?><option value="<?= (int) $item['id'] ?>"><?= esc($item['name']) ?> (<?= esc($item['base_unit']) ?>)</option><?php endforeach ?></select></div>
        <div><label class="form-label" for="line-qty">Jumlah</label><input class="form-input" id="line-qty" name="quantity" type="number" min="0.001" step="0.001" required></div>
        <button class="button-primary" type="submit">Simpan bahan</button>
    </form>
</section>
<?php endif ?>
