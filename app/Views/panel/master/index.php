<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<div class="mb-8">
    <p class="section-kicker">Master persediaan</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Bahan, resep, dan supplier</h1>
    <p class="mt-3 max-w-3xl text-ink-muted">Tentukan satuan dasar bahan, konversi pembelian, lalu susun pemakaian bahan untuk setiap varian berbasis resep.</p>
</div>

<div class="space-y-8">
    <section class="grid gap-6">
        <div class="panel-card overflow-hidden">
            <div class="flex items-start justify-between gap-3 border-b border-border-warm px-5 py-4"><div><h2 class="font-bold">Daftar bahan</h2><p class="mt-1 text-sm text-ink-muted">Saldo awal akan dicatat pada tahap operasional agar memiliki jejak mutasi.</p></div><a class="button-primary !min-h-9 !px-3" href="<?= site_url('panel/form/item') ?>">Tambah bahan</a></div>
            <?php if ($items === []): ?>
                <div class="p-6"><p class="font-display text-xl">Belum ada bahan.</p><p class="mt-2 text-sm text-ink-muted">Tambahkan bahan pertama untuk mulai menyusun resep.</p></div>
            <?php else: ?>
                <div class="divide-y divide-border-warm">
                    <?php foreach ($items as $item): ?>
                        <article class="grid gap-4 p-5 lg:grid-cols-[1fr_auto] lg:items-start">
                            <div><div class="flex flex-wrap items-center gap-2"><h3 class="font-bold"><?= esc($item['name']) ?></h3><span class="status-label bg-surface-soft-yellow"><?= esc($item['sku']) ?></span></div><p class="mt-1 text-sm text-ink-muted">Satuan dasar: <?= esc($item['base_unit']) ?> · Minimum <?= number_format((float) $item['minimum_stock'], 3, ',', '.') ?></p>
                                <?php $itemConversions = array_filter($conversions, static fn ($conversion) => (int) $conversion['inventory_item_id'] === (int) $item['id']); ?>
                                <?php if ($itemConversions !== []): ?><p class="mt-2 text-xs text-ink-muted"><?php foreach ($itemConversions as $conversion): ?>1 <?= esc($conversion['purchase_unit']) ?> = <?= number_format((float) $conversion['factor_to_base'], 3, ',', '.') ?> <?= esc($item['base_unit']) ?>&nbsp;&nbsp;<?php endforeach ?></p><?php endif ?>
                            </div>
                            <form action="<?= site_url('panel/master/items/status') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><button class="status-button <?= $item['is_active'] ? 'is-active' : '' ?>" type="submit"><?= $item['is_active'] ? 'Aktif' : 'Nonaktif' ?></button></form>
                            <a class="inline-flex min-h-11 items-center text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-4 lg:col-span-2" href="<?= site_url('panel/form/item/' . (int) $item['id']) ?>">Edit bahan</a>
                            <form action="<?= site_url('panel/master/conversions') ?>" method="post" class="grid gap-3 border-t border-border-warm pt-4 lg:col-span-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                <?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                <div><label class="form-label" for="purchase-unit-<?= (int) $item['id'] ?>">Satuan pembelian</label><input class="form-input" id="purchase-unit-<?= (int) $item['id'] ?>" name="purchase_unit" placeholder="Contoh: kg" required></div>
                                <div><label class="form-label" for="factor-<?= (int) $item['id'] ?>">Isi dalam <?= esc($item['base_unit']) ?></label><input class="form-input" id="factor-<?= (int) $item['id'] ?>" name="factor_to_base" type="number" min="0.000001" step="0.000001" placeholder="1000" required></div>
                                <button class="button-secondary" type="submit">Simpan konversi</button>
                            </form>
                        </article>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </section>

    <section>
        <div class="mb-4"><h2 class="text-xl font-bold">Resep per varian</h2><p class="mt-1 text-sm text-ink-muted">Jumlah selalu memakai satuan dasar bahan untuk satu porsi penjualan.</p></div>
        <?php if ($variants === [] || $items === []): ?>
            <div class="panel-card p-6"><p class="font-semibold">Resep belum dapat disusun.</p><p class="mt-1 text-sm text-ink-muted">Pastikan ada varian dengan mode resep dan minimal satu bahan aktif.</p></div>
        <?php else: ?>
            <div class="grid gap-5 lg:grid-cols-2">
                <?php foreach ($variants as $variant): ?>
                    <?php $recipeItems = $recipesByVariant[$variant['id']] ?? []; ?>
                    <article class="panel-card p-5 sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-wider text-brand-chili"><?= esc($variant['sku']) ?></p>
                        <h3 class="mt-1 font-display text-xl"><?= esc($variant['product_name']) ?> · <?= esc($variant['variant_name']) ?></h3>
                        <?php if ($recipeItems === []): ?><p class="mt-4 border border-border-warm bg-surface-soft-yellow p-3 text-sm text-ink-muted">Belum ada bahan pada resep ini.</p><?php else: ?>
                            <ul class="mt-4 divide-y divide-border-warm border-y border-border-warm">
                                <?php foreach ($recipeItems as $recipeItem): ?><li class="flex items-center justify-between gap-3 py-3 text-sm"><span><strong><?= esc($recipeItem['item_name']) ?></strong> · <?= number_format((float) $recipeItem['quantity'], 3, ',', '.') ?> <?= esc($recipeItem['base_unit']) ?></span><form action="<?= site_url('panel/master/recipes/remove') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="recipe_item_id" value="<?= (int) $recipeItem['id'] ?>"><button class="min-h-11 px-3 font-bold text-status-danger underline" type="submit">Hapus</button></form></li><?php endforeach ?>
                            </ul>
                        <?php endif ?>
                        <form action="<?= site_url('panel/master/recipes') ?>" method="post" class="mt-5 grid gap-3 sm:grid-cols-[1fr_9rem]">
                            <?= csrf_field() ?><input type="hidden" name="variant_id" value="<?= (int) $variant['id'] ?>">
                            <div><label class="form-label" for="recipe-item-<?= (int) $variant['id'] ?>">Bahan</label><select class="form-input" id="recipe-item-<?= (int) $variant['id'] ?>" name="item_id" required><option value="">Pilih bahan</option><?php foreach ($items as $item): ?><?php if ($item['is_active']): ?><option value="<?= (int) $item['id'] ?>"><?= esc($item['name']) ?> (<?= esc($item['base_unit']) ?>)</option><?php endif ?><?php endforeach ?></select></div>
                            <div><label class="form-label" for="recipe-quantity-<?= (int) $variant['id'] ?>">Jumlah</label><input class="form-input" id="recipe-quantity-<?= (int) $variant['id'] ?>" name="quantity" type="number" min="0.001" step="0.001" required></div>
                            <div class="sm:col-span-2"><button class="button-primary" type="submit">Simpan bahan resep</button></div>
                        </form>
                    </article>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>

    <section>
        <div class="mb-4 flex items-start justify-between gap-3"><div><h2 class="text-xl font-bold">Topping</h2><p class="mt-1 text-sm text-ink-muted">Harga tambahan dan bahan topping akan dipakai saat POS dibangun.</p></div><a class="button-primary !min-h-9 !px-3" href="<?= site_url('panel/form/topping') ?>">Tambah topping</a></div>
        <div class="grid gap-6">
            <div class="space-y-4">
                <?php if ($toppings === []): ?><div class="panel-card p-6"><p class="font-semibold">Belum ada topping.</p><p class="mt-1 text-sm text-ink-muted">Tambahkan topping bila pelanggan dapat memilih tambahan.</p></div><?php endif ?>
                <?php foreach ($toppings as $topping): ?>
                    <?php $toppingItems = $toppingItemsByTopping[$topping['id']] ?? []; ?>
                    <article class="panel-card p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-4"><div><h3 class="font-display text-xl"><?= esc($topping['name']) ?></h3><p class="mt-1 text-sm text-ink-muted"><?= esc($topping['sku']) ?> · tambahan Rp<?= number_format((float) $topping['additional_price'], 0, ',', '.') ?></p></div><form action="<?= site_url('panel/master/toppings/status') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="topping_id" value="<?= (int) $topping['id'] ?>"><button class="status-button <?= $topping['is_active'] ? 'is-active' : '' ?>" type="submit"><?= $topping['is_active'] ? 'Aktif' : 'Nonaktif' ?></button></form></div>
                        <a class="inline-flex min-h-11 items-center text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-4 " href="<?= site_url('panel/form/topping/' . (int) $topping['id']) ?>">Edit topping</a>
                        <?php if ($toppingItems !== []): ?><ul class="mt-4 flex flex-wrap gap-2"><?php foreach ($toppingItems as $item): ?><li class="status-label bg-surface-soft-yellow"><?= esc($item['item_name']) ?> <?= number_format((float) $item['quantity'], 3, ',', '.') ?> <?= esc($item['base_unit']) ?></li><?php endforeach ?></ul><?php endif ?>
                        <?php if ($items !== []): ?><form action="<?= site_url('panel/master/toppings/items') ?>" method="post" class="mt-5 grid gap-3 border-t border-border-warm pt-4 sm:grid-cols-[1fr_9rem_auto] sm:items-end"><?= csrf_field() ?><input type="hidden" name="topping_id" value="<?= (int) $topping['id'] ?>"><div><label class="form-label" for="topping-item-<?= (int) $topping['id'] ?>">Bahan</label><select class="form-input" id="topping-item-<?= (int) $topping['id'] ?>" name="item_id" required><option value="">Pilih bahan</option><?php foreach ($items as $item): ?><?php if ($item['is_active']): ?><option value="<?= (int) $item['id'] ?>"><?= esc($item['name']) ?> (<?= esc($item['base_unit']) ?>)</option><?php endif ?><?php endforeach ?></select></div><div><label class="form-label" for="topping-qty-<?= (int) $topping['id'] ?>">Jumlah</label><input class="form-input" id="topping-qty-<?= (int) $topping['id'] ?>" name="quantity" type="number" min="0.001" step="0.001" required></div><button class="button-secondary" type="submit">Simpan bahan</button></form><?php endif ?>
                    </article>
                <?php endforeach ?>
            </div>
        </div>
    </section>

    <section class="grid gap-6">
        <div class="panel-card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-border-warm px-5 py-4"><h2 class="font-bold">Daftar supplier</h2><a class="button-primary !min-h-9 !px-3" href="<?= site_url('panel/form/supplier') ?>">Tambah supplier</a></div>
            <?php if ($suppliers === []): ?><p class="p-6 text-ink-muted">Belum ada supplier.</p><?php else: ?><div class="divide-y divide-border-warm"><?php foreach ($suppliers as $supplier): ?><article class="p-5"><div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="font-bold"><?= esc($supplier['name']) ?></h3><?php if ($supplier['contact_name'] || $supplier['phone']): ?><p class="mt-1 text-sm text-ink-muted"><?= esc(trim(($supplier['contact_name'] ?? '') . ' ' . ($supplier['phone'] ?? ''))) ?></p><?php endif ?><?php if ($supplier['address']): ?><p class="mt-1 text-sm text-ink-muted"><?= esc($supplier['address']) ?></p><?php endif ?></div><form action="<?= site_url('panel/master/suppliers/status') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= (int) $supplier['id'] ?>"><button class="status-button <?= $supplier['is_active'] ? 'is-active' : '' ?>" type="submit"><?= $supplier['is_active'] ? 'Aktif' : 'Nonaktif' ?></button></form></div><a class="inline-flex min-h-11 items-center text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-4 " href="<?= site_url('panel/form/supplier/' . (int) $supplier['id']) ?>">Edit supplier</a></article><?php endforeach ?></div><?php endif ?>
        </div>
    </section>
</div>
<?= $this->endSection() ?>
