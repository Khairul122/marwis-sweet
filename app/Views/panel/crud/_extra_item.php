<?php $itemId = (int) $hidden['item_id']; $editUrl = 'panel/data/item/' . $itemId . '/edit'; $unit = $row['base_unit']; ?>
<section class="panel-card mt-8 max-w-3xl overflow-hidden">
    <div class="border-b border-border-warm px-5 py-4"><h2 class="font-bold uppercase tracking-wide">Konversi satuan pembelian</h2><p class="mt-1 text-sm text-ink-muted">Dipakai saat penerimaan stok, misalnya 1 kg = 1000 <?= esc($unit) ?>.</p></div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[28rem] text-left text-sm">
            <thead class="bg-surface-soft-yellow"><tr><th class="px-4 py-3 font-bold">No</th><th class="px-4 py-3 font-bold">Satuan pembelian</th><th class="px-4 py-3 text-right font-bold">Isi (<?= esc($unit) ?>)</th><th class="px-4 py-3 text-right font-bold">Aksi</th></tr></thead>
            <tbody class="divide-y divide-border-warm">
                <?php foreach ($extra['conversions'] as $i => $conversion): ?>
                    <tr><td class="px-4 py-3"><?= $i + 1 ?></td><td class="px-4 py-3 font-semibold"><?= esc($conversion['purchase_unit']) ?></td><td class="px-4 py-3 text-right tabular-nums"><?= number_format((float) $conversion['factor_to_base'], 3, ',', '.') ?></td>
                        <td class="px-4 py-3 text-right"><form action="<?= site_url('panel/master/conversions/remove') ?>" method="post" data-confirm-form="Hapus konversi satuan ini?"><?= csrf_field() ?><input type="hidden" name="conversion_id" value="<?= (int) $conversion['id'] ?>"><input type="hidden" name="_return" value="<?= esc($editUrl) ?>"><button class="button-danger" type="submit">Hapus</button></form></td></tr>
                <?php endforeach ?>
                <?php if ($extra['conversions'] === []): ?><tr><td class="px-4 py-6 text-ink-muted" colspan="4">Belum ada konversi.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
    <form action="<?= site_url('panel/master/conversions') ?>" method="post" class="grid gap-4 border-t border-border-warm p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end sm:p-6">
        <?= csrf_field() ?><input type="hidden" name="item_id" value="<?= $itemId ?>"><input type="hidden" name="_return" value="<?= esc($editUrl) ?>">
        <div><label class="form-label" for="purchase-unit">Satuan pembelian</label><input class="form-input" id="purchase-unit" name="purchase_unit" placeholder="Contoh: kg" required maxlength="30"></div>
        <div><label class="form-label" for="factor">Isi dalam <?= esc($unit) ?></label><input class="form-input" id="factor" name="factor_to_base" type="number" min="0.001" step="0.001" required></div>
        <button class="button-primary" type="submit">Simpan konversi</button>
    </form>
</section>
