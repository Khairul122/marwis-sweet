<?php $productId = (int) $hidden['product_id']; $editUrl = 'panel/data/product/' . $productId . '/edit'; ?>
<section class="panel-card mt-8 max-w-3xl overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-warm px-5 py-4">
        <h2 class="font-bold uppercase tracking-wide">Varian dan harga</h2>
        <a class="button-primary button-sm" href="<?= site_url('panel/data/variant/create?product_id=' . $productId) ?>">Tambah varian</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[32rem] text-left text-sm">
            <thead class="bg-surface-soft-yellow"><tr><th class="px-4 py-3 font-bold">No</th><th class="px-4 py-3 font-bold">Varian</th><th class="px-4 py-3 font-bold">SKU</th><th class="px-4 py-3 text-right font-bold">Harga</th><th class="px-4 py-3 font-bold">Status</th><th class="px-4 py-3 text-right font-bold">Aksi</th></tr></thead>
            <tbody class="divide-y divide-border-warm">
                <?php foreach ($extra['variants'] as $i => $variant): ?>
                    <tr><td class="px-4 py-3"><?= $i + 1 ?></td><td class="px-4 py-3 font-semibold"><?= esc($variant['name']) ?></td><td class="px-4 py-3"><?= esc($variant['sku']) ?></td><td class="px-4 py-3 text-right tabular-nums">Rp<?= number_format((float) $variant['price'], 0, ',', '.') ?></td><td class="px-4 py-3"><?= $variant['is_active'] ? 'Aktif' : 'Nonaktif' ?></td><td class="px-4 py-3 text-right"><a class="button-secondary button-sm" href="<?= site_url('panel/data/variant/' . (int) $variant['id'] . '/edit') ?>">Edit</a></td></tr>
                <?php endforeach ?>
                <?php if ($extra['variants'] === []): ?><tr><td class="px-4 py-6 text-ink-muted" colspan="6">Belum ada varian.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel-card mt-8 max-w-3xl overflow-hidden">
    <div class="border-b border-border-warm px-5 py-4"><h2 class="font-bold uppercase tracking-wide">Foto produk (<?= count($extra['images']) ?>/5)</h2></div>
    <div class="p-5 sm:p-6">
        <?php if ($extra['images'] !== []): ?><div class="mb-5 flex gap-3 overflow-x-auto pb-2"><?php foreach ($extra['images'] as $image): ?><img class="h-24 w-32 shrink-0 border border-border-warm object-cover" src="<?= base_url($image['image_path']) ?>" alt="<?= esc($image['alt_text']) ?>"><?php endforeach ?></div><?php endif ?>
        <?php if (count($extra['images']) < 5): ?>
            <form action="<?= site_url('panel/catalog/products/images') ?>" method="post" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="_return" value="<?= esc($editUrl) ?>">
                <div><label class="form-label" for="photo">Foto JPEG, PNG, atau WebP</label><input class="form-input file:mr-3 file:border-0 file:bg-brand-sun file:px-3 file:py-1 file:font-bold" id="photo" name="image" type="file" accept="image/jpeg,image/png,image/webp" required></div>
                <div><label class="form-label" for="alt">Deskripsi foto</label><input class="form-input" id="alt" name="alt_text" placeholder="Contoh: Martabak cokelat keju" minlength="5" maxlength="180" required></div>
                <button class="button-primary" type="submit">Unggah foto</button>
            </form>
        <?php endif ?>
    </div>
</section>
