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
        <?php if ($extra['images'] !== []): ?><ol class="mb-6 grid gap-4 sm:grid-cols-2" aria-label="Urutan foto produk">
            <?php foreach ($extra['images'] as $position => $image): ?>
                <li class="border border-border-warm bg-white p-3">
                    <div class="mb-3" data-image-preview-group><img class="aspect-[4/3] w-full border border-border-warm object-cover" data-image-preview src="<?= base_url($image['image_path']) ?>" alt="Pratinjau <?= esc($image['alt_text']) ?>"></div>
                    <form action="<?= site_url('panel/catalog/products/images/update') ?>" method="post" enctype="multipart/form-data" class="space-y-3" data-image-preview-group>
                        <?= csrf_field() ?><input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                        <div><label class="form-label" for="alt-<?= (int) $image['id'] ?>">Teks alternatif</label><input class="form-input" id="alt-<?= (int) $image['id'] ?>" name="alt_text" value="<?= esc($image['alt_text']) ?>" minlength="5" maxlength="180" required></div>
                        <div><label class="form-label" for="replace-<?= (int) $image['id'] ?>">Ganti gambar (opsional, maks. 2 MB)</label><input class="form-input file:mr-2 file:border-0 file:bg-brand-sun file:px-2 file:py-1 file:font-bold" id="replace-<?= (int) $image['id'] ?>" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-image-preview-input><p class="mt-1 text-xs text-ink-muted">JPEG, PNG, atau WebP</p><img class="mt-2 hidden aspect-[4/3] w-full border border-border-warm object-cover" data-image-preview alt="Pratinjau gambar pengganti"></div>
                        <button class="button-secondary w-full" type="submit">Simpan foto</button>
                    </form>
                    <div class="mt-3 flex gap-2">
                        <?php foreach (['up' => 'Naikkan', 'down' => 'Turunkan'] as $direction => $label): ?><form action="<?= site_url('panel/catalog/products/images/order') ?>" method="post" class="flex-1"><?= csrf_field() ?><input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>"><input type="hidden" name="direction" value="<?= esc($direction) ?>"><button class="button-secondary w-full" type="submit" <?= ($direction === 'up' && $position === 0) || ($direction === 'down' && $position === count($extra['images']) - 1) ? 'disabled' : '' ?>><?= esc($label) ?></button></form><?php endforeach ?>
                    </div>
                    <form action="<?= site_url('panel/catalog/products/images/delete') ?>" method="post" class="mt-2" data-confirm-form="Hapus foto ini dari galeri produk?"><?= csrf_field() ?><input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>"><button class="button-danger w-full" type="submit">Hapus foto</button></form>
                </li>
            <?php endforeach ?>
        </ol><?php endif ?>
        <?php if (count($extra['images']) < 5): ?>
            <form action="<?= site_url('panel/catalog/products/images') ?>" method="post" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end" data-image-preview-group>
                <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="_return" value="panel/data/product/<?= $productId ?>/edit">
                <div><label class="form-label" for="photo">Foto JPEG, PNG, atau WebP</label><input class="form-input file:mr-3 file:border-0 file:bg-brand-sun file:px-3 file:py-1 file:font-bold" id="photo" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-image-preview-input required><img class="mt-2 hidden aspect-[4/3] w-48 border border-border-warm object-cover" data-image-preview alt="Pratinjau foto produk baru"></div>
                <div><label class="form-label" for="alt">Deskripsi foto</label><input class="form-input" id="alt" name="alt_text" placeholder="Contoh: Martabak cokelat keju" minlength="5" maxlength="180" required></div>
                <button class="button-primary" type="submit">Unggah foto</button>
            </form>
        <?php endif ?>
    </div>
</section>
