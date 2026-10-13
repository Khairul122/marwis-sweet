<?php $toppingId = (int) $hidden['topping_id']; $editUrl = 'panel/data/topping/' . $toppingId . '/edit'; ?>
<section class="panel-card mt-8 max-w-3xl overflow-hidden">
    <div class="border-b border-border-warm px-5 py-4"><h2 class="font-bold uppercase tracking-wide">Bahan yang dipakai topping</h2></div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[28rem] text-left text-sm">
            <thead class="bg-surface-soft-yellow"><tr><th class="px-4 py-3 font-bold">No</th><th class="px-4 py-3 font-bold">Bahan</th><th class="px-4 py-3 text-right font-bold">Jumlah</th><th class="px-4 py-3 text-right font-bold">Aksi</th></tr></thead>
            <tbody class="divide-y divide-border-warm">
                <?php foreach ($extra['lines'] as $i => $line): ?>
                    <tr><td class="px-4 py-3"><?= $i + 1 ?></td><td class="px-4 py-3 font-semibold"><?= esc($line['item_name']) ?></td><td class="px-4 py-3 text-right tabular-nums"><?= number_format((float) $line['quantity'], 3, ',', '.') ?> <?= esc($line['base_unit']) ?></td>
                        <td class="px-4 py-3 text-right"><form action="<?= site_url('panel/master/toppings/items/remove') ?>" method="post" data-confirm-form="Hapus bahan ini dari topping?"><?= csrf_field() ?><input type="hidden" name="topping_item_id" value="<?= (int) $line['id'] ?>"><input type="hidden" name="_return" value="<?= esc($editUrl) ?>"><button class="button-danger" type="submit">Hapus</button></form></td></tr>
                <?php endforeach ?>
                <?php if ($extra['lines'] === []): ?><tr><td class="px-4 py-6 text-ink-muted" colspan="4">Belum ada bahan.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($extra['items'] !== []): ?>
    <form action="<?= site_url('panel/master/toppings/items') ?>" method="post" class="grid gap-4 border-t border-border-warm p-5 sm:grid-cols-[1fr_9rem_auto] sm:items-end sm:p-6">
        <?= csrf_field() ?><input type="hidden" name="topping_id" value="<?= $toppingId ?>"><input type="hidden" name="_return" value="<?= esc($editUrl) ?>">
        <div><label class="form-label" for="t-item">Bahan</label><select class="form-input" id="t-item" name="item_id" required><option value="">Pilih bahan</option><?php foreach ($extra['items'] as $item): ?><option value="<?= (int) $item['id'] ?>"><?= esc($item['name']) ?> (<?= esc($item['base_unit']) ?>)</option><?php endforeach ?></select></div>
        <div><label class="form-label" for="t-qty">Jumlah</label><input class="form-input" id="t-qty" name="quantity" type="number" min="0.001" step="0.001" required></div>
        <button class="button-primary" type="submit">Simpan bahan</button>
    </form>
    <?php endif ?>
</section>
