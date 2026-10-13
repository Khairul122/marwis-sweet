<?php
$productId = (int) $hidden['product_id'];
$variantRows = array_map(static fn (array $v): array => [
    '<strong>' . esc($v['name']) . '</strong>', esc($v['sku']), panel_rupiah($v['price']), panel_badge((bool) $v['is_active']),
    panel_actions(['edit' => 'panel/data/variant/' . $v['id'] . '/edit']),
], $extra['variants']);
?>
<div class="mt-8 max-w-3xl">
    <?= panel_table('Varian dan harga', ['Varian', 'SKU', '>Harga', 'Status', '>Aksi'], $variantRows, ['search' => false, 'createUrl' => 'panel/data/variant/create?product_id=' . $productId, 'create' => 'Tambah varian', 'empty' => 'Belum ada varian.', 'width' => '32rem']) ?>
</div>

<section class="panel-card mt-8 max-w-3xl overflow-hidden">
    <div class="border-b border-border-warm px-5 py-4"><h2 class="font-bold uppercase tracking-wide">Foto produk (<?= count($extra['images']) ?>/5)</h2></div>
    <div class="p-5 sm:p-6">
        <?php if ($extra['images'] !== []): ?><div class="mb-5 flex gap-3 overflow-x-auto pb-2"><?php foreach ($extra['images'] as $image): ?><img class="h-24 w-32 shrink-0 border border-border-warm object-cover" src="<?= base_url($image['image_path']) ?>" alt="<?= esc($image['alt_text']) ?>"><?php endforeach ?></div><?php endif ?>
        <?php if (count($extra['images']) < 5): ?>
            <form action="<?= site_url('panel/catalog/products/images') ?>" method="post" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="_return" value="panel/data/product/<?= $productId ?>/edit">
                <div><label class="form-label" for="photo">Foto JPEG, PNG, atau WebP</label><input class="form-input file:mr-3 file:border-0 file:bg-brand-sun file:px-3 file:py-1 file:font-bold" id="photo" name="image" type="file" accept="image/jpeg,image/png,image/webp" required></div>
                <div><label class="form-label" for="alt">Deskripsi foto</label><input class="form-input" id="alt" name="alt_text" placeholder="Contoh: Martabak cokelat keju" minlength="5" maxlength="180" required></div>
                <button class="button-primary" type="submit">Unggah foto</button>
            </form>
        <?php endif ?>
    </div>
</section>
