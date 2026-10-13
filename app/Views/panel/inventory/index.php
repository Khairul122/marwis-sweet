<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<div class="mb-8">
    <p class="section-kicker">Operasional stok</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Penerimaan dan kartu stok</h1>
    <p class="mt-3 max-w-3xl text-ink-muted">Siapkan penerimaan sebagai draft, periksa kuantitas dan biaya, lalu posting untuk memperbarui stok dan biaya rata-rata.</p>
</div>

<section class="mb-8">
    <div class="mb-4"><h2 class="text-xl font-bold">Posisi bahan</h2><p class="mt-1 text-sm text-ink-muted">Saldo berikut hanya berubah melalui dokumen yang sudah diposting.</p></div>
    <?php if ($items === []): ?><div class="panel-card p-6"><p class="font-semibold">Belum ada bahan aktif.</p><a class="mt-2 inline-block font-bold underline" href="<?= site_url('panel/master') ?>">Buka master bahan</a></div><?php else: ?>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($items as $item): ?>
                <?php $isLow = (float) $item['quantity_on_hand'] <= (float) $item['minimum_stock']; ?>
                <article class="panel-card p-5"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-bold"><?= esc($item['name']) ?></p><p class="mt-1 text-xs text-ink-muted"><?= esc($item['sku']) ?></p></div><span class="status-label <?= $isLow ? 'bg-[#FFF7ED] text-status-warning' : 'bg-[#F0FDF4] text-status-success' ?>"><?= $isLow ? 'Perlu restok' : 'Stok aman' ?></span></div><p class="mt-5 font-display text-3xl tabular-nums"><?= number_format((float) $item['quantity_on_hand'], 3, ',', '.') ?> <span class="text-base"><?= esc($item['base_unit']) ?></span></p><p class="mt-2 text-sm text-ink-muted">Biaya rata-rata Rp<?= number_format((float) $item['average_cost'], 2, ',', '.') ?>/<?= esc($item['base_unit']) ?></p></article>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>

<div class="grid gap-6 xl:grid-cols-[23rem_minmax(0,1fr)] xl:items-start">
    <section class="panel-card p-5 sm:p-6 xl:sticky xl:top-24">
        <h2 class="text-lg font-bold">Buat draft penerimaan</h2>
        <p class="mt-1 text-sm text-ink-muted">Catat bahan yang datang, lalu tambahkan itemnya pada dokumen.</p>
        <a class="button-primary mt-5 w-full" href="<?= site_url('panel/data/receipt/create') ?>">Tambah draft penerimaan</a>
    </section>

    <section>
        <div class="mb-4"><h2 class="text-xl font-bold">Dokumen penerimaan</h2><p class="mt-1 text-sm text-ink-muted"><?= count($receipts) ?> dokumen terbaru</p></div>
        <?php if ($receipts === []): ?><div class="panel-card p-8"><p class="font-display text-2xl">Belum ada penerimaan.</p><p class="mt-2 text-ink-muted">Buat draft pertama saat bahan datang.</p></div><?php else: ?>
            <div class="space-y-4">
                <?php foreach ($receipts as $receipt): ?>
                    <?php $lines = $itemsByReceipt[$receipt['id']] ?? []; $total = array_sum(array_map(static fn ($line) => (float) $line['line_total'], $lines)); ?>
                    <article class="panel-card overflow-hidden">
                        <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[1fr_auto]">
                            <div><div class="flex flex-wrap items-center gap-2"><h3 class="font-display text-xl"><?= esc($receipt['reference_number']) ?></h3><span class="status-label <?= $receipt['status'] === 'posted' ? 'bg-[#F0FDF4] text-status-success' : 'bg-surface-soft-yellow' ?>"><?= $receipt['status'] === 'posted' ? 'Sudah diposting' : 'Draft' ?></span></div><p class="mt-2 text-sm text-ink-muted"><?= esc(date('d/m/Y', strtotime($receipt['document_date']))) ?> · <?= esc($receipt['supplier_name'] ?: 'Tanpa supplier') ?> · dibuat oleh <?= esc($receipt['creator_name']) ?></p></div>
                            <div class="text-left lg:text-right"><p class="text-sm text-ink-muted">Total penerimaan</p><p class="mt-1 text-xl font-black tabular-nums">Rp<?= number_format($total, 0, ',', '.') ?></p></div>
                        </div>
                        <?php if ($lines !== []): ?><div class="overflow-x-auto border-t border-border-warm"><table class="w-full min-w-[42rem] text-sm"><thead class="bg-surface-soft-yellow text-left"><tr><th class="px-5 py-3">Bahan</th><th class="px-5 py-3">Pembelian</th><th class="px-5 py-3">Masuk stok</th><th class="px-5 py-3 text-right">Biaya</th></tr></thead><tbody class="divide-y divide-border-warm"><?php foreach ($lines as $line): ?><tr><td class="px-5 py-3 font-semibold"><?= esc($line['item_name']) ?></td><td class="px-5 py-3"><?= number_format((float) $line['purchase_quantity'], 3, ',', '.') ?> <?= esc($line['purchase_unit']) ?></td><td class="px-5 py-3"><?= number_format((float) $line['base_quantity'], 3, ',', '.') ?> <?= esc($line['base_unit']) ?></td><td class="px-5 py-3 text-right tabular-nums">Rp<?= number_format((float) $line['line_total'], 0, ',', '.') ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?>
                        <?php if ($receipt['status'] === 'draft'): ?>
                            <div class="border-t border-border-warm bg-[#FFFCF4] p-5 sm:p-6">
                                <form action="<?= site_url('panel/inventory/receipts/items') ?>" method="post" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                                    <?= csrf_field() ?><input type="hidden" name="receipt_id" value="<?= (int) $receipt['id'] ?>">
                                    <div><label class="form-label" for="receipt-item-<?= (int) $receipt['id'] ?>">Bahan</label><select class="form-input" id="receipt-item-<?= (int) $receipt['id'] ?>" name="item_id" required><option value="">Pilih bahan</option><?php foreach ($items as $item): ?><option value="<?= (int) $item['id'] ?>"><?= esc($item['name']) ?></option><?php endforeach ?></select></div>
                                    <div><label class="form-label" for="purchase-qty-<?= (int) $receipt['id'] ?>">Jumlah beli</label><input class="form-input" id="purchase-qty-<?= (int) $receipt['id'] ?>" name="purchase_quantity" type="number" min="0.001" step="0.001" required></div>
                                    <div><label class="form-label" for="purchase-unit-<?= (int) $receipt['id'] ?>">Satuan beli</label><input class="form-input" id="purchase-unit-<?= (int) $receipt['id'] ?>" name="purchase_unit" placeholder="kg, liter, pcs" required></div>
                                    <div><label class="form-label" for="purchase-cost-<?= (int) $receipt['id'] ?>">Biaya per satuan</label><input class="form-input" id="purchase-cost-<?= (int) $receipt['id'] ?>" name="purchase_unit_cost" type="number" min="0" step="0.01" required></div>
                                    <button class="button-secondary" type="submit">Simpan item</button>
                                </form>
                                <form action="<?= site_url('panel/inventory/receipts/post') ?>" method="post" class="mt-5 border-t border-border-warm pt-5" data-confirm-form="Posting penerimaan akan menambah stok dan mengunci dokumen. Lanjutkan?">
                                    <?= csrf_field() ?><input type="hidden" name="receipt_id" value="<?= (int) $receipt['id'] ?>"><button class="button-primary <?= $lines === [] ? 'cursor-not-allowed opacity-50' : '' ?>" type="submit" <?= $lines === [] ? 'disabled aria-disabled="true"' : '' ?>>Posting penerimaan</button>
                                </form>
                            </div>
                        <?php endif ?>
                    </article>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>
</div>

<section class="mt-10">
    <div class="mb-4"><h2 class="text-xl font-bold">Kartu stok terbaru</h2><p class="mt-1 text-sm text-ink-muted">Mutasi yang sudah diposting tidak dapat diedit.</p></div>
    <div class="panel-card overflow-x-auto">
        <?php if ($movements === []): ?><p class="p-6 text-ink-muted">Belum ada mutasi stok.</p><?php else: ?><table class="w-full min-w-[48rem] text-sm"><thead class="bg-surface-soft-yellow text-left"><tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Bahan</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3 text-right">Kuantitas</th><th class="px-5 py-3 text-right">Nilai</th><th class="px-5 py-3">Referensi</th></tr></thead><tbody class="divide-y divide-border-warm"><?php foreach ($movements as $movement): ?><tr><td class="px-5 py-3"><?= esc(date('d/m/Y H:i', strtotime($movement['posted_at']))) ?></td><td class="px-5 py-3 font-semibold"><?= esc($movement['item_name']) ?></td><td class="px-5 py-3"><?= $movement['movement_type'] === 'receipt' ? 'Penerimaan' : esc(ucfirst($movement['movement_type'])) ?></td><td class="px-5 py-3 text-right font-bold tabular-nums <?= (float) $movement['quantity'] >= 0 ? 'text-status-success' : 'text-status-danger' ?>"><?= (float) $movement['quantity'] >= 0 ? '+' : '' ?><?= number_format((float) $movement['quantity'], 3, ',', '.') ?> <?= esc($movement['base_unit']) ?></td><td class="px-5 py-3 text-right tabular-nums">Rp<?= number_format((float) $movement['total_cost'], 0, ',', '.') ?></td><td class="px-5 py-3"><?= esc($movement['notes']) ?></td></tr><?php endforeach ?></tbody></table><?php endif ?>
    </div>
</section>
<?= $this->endSection() ?>
