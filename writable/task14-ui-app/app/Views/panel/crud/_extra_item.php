<?php
$itemId = (int) $hidden['item_id'];
$returnUrl = 'panel/data/item/' . $itemId . '/edit';
$unit = $row['base_unit'];
$rows = array_map(static fn (array $c): array => [
    '<strong>' . esc($c['purchase_unit']) . '</strong>', panel_qty($c['factor_to_base']),
    panel_post('panel/master/conversions/remove', ['conversion_id' => $c['id'], '_return' => $returnUrl], 'Hapus', 'button-danger', 'Hapus konversi satuan ini?'),
], $extra['conversions']);
?>
<div class="mt-8 max-w-3xl">
    <?= panel_table('Konversi satuan pembelian', ['Satuan pembelian', '>Isi (' . $unit . ')', '>Aksi'], $rows, ['search' => false, 'empty' => 'Belum ada konversi. Contoh: 1 kg = 1000 ' . $unit . '.', 'width' => '28rem']) ?>
    <section class="panel-card -mt-4 mb-8 p-5 sm:p-6">
        <form action="<?= site_url('panel/master/conversions') ?>" method="post" class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <?= csrf_field() ?><input type="hidden" name="item_id" value="<?= $itemId ?>"><input type="hidden" name="_return" value="<?= esc($returnUrl) ?>">
            <div><label class="form-label" for="purchase-unit">Satuan pembelian</label><input class="form-input" id="purchase-unit" name="purchase_unit" placeholder="Contoh: kg" required maxlength="30"></div>
            <div><label class="form-label" for="factor">Isi dalam <?= esc($unit) ?></label><input class="form-input" id="factor" name="factor_to_base" type="number" min="0.001" step="0.001" required></div>
            <button class="button-primary" type="submit">Simpan konversi</button>
        </form>
    </section>
</div>
