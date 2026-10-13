<?php $bannerImage = $row['image_path'] ?? null; ?>
<section class="sm:col-span-2" data-image-preview-group aria-labelledby="banner-preview-title">
    <h2 class="font-bold" id="banner-preview-title">Pratinjau banner</h2>
    <p class="mt-1 text-sm text-ink-muted">Periksa pemotongan gambar pada tampilan lebar dan ponsel sebelum menyimpan.</p>
    <div class="mt-4 grid items-start gap-5 sm:grid-cols-[minmax(0,1fr)_14rem]">
        <figure><figcaption class="mb-2 text-xs font-bold uppercase tracking-wide text-ink-muted">Desktop · 16:5</figcaption><div class="aspect-[16/5] overflow-hidden border border-border-warm bg-surface-soft-yellow"><?php if ($bannerImage): ?><img class="h-full w-full object-cover" data-image-preview src="<?= base_url($bannerImage) ?>" alt="Pratinjau banner desktop"><?php else: ?><img class="hidden h-full w-full object-cover" data-image-preview alt="Pratinjau banner desktop"><span class="grid h-full place-items-center text-sm text-ink-muted" data-image-preview-empty>Pilih gambar untuk melihat pratinjau</span><?php endif ?></div></figure>
        <figure><figcaption class="mb-2 text-xs font-bold uppercase tracking-wide text-ink-muted">Ponsel · 4:3</figcaption><div class="aspect-[4/3] overflow-hidden border border-border-warm bg-surface-soft-yellow"><?php if ($bannerImage): ?><img class="h-full w-full object-cover" data-image-preview src="<?= base_url($bannerImage) ?>" alt="Pratinjau banner ponsel"><?php else: ?><img class="hidden h-full w-full object-cover" data-image-preview alt="Pratinjau banner ponsel"><span class="grid h-full place-items-center p-3 text-center text-sm text-ink-muted" data-image-preview-empty>Pilih gambar untuk melihat pratinjau</span><?php endif ?></div></figure>
    </div>
</section>
