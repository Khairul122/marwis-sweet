<?= $this->extend('layouts/pos') ?>
<?= $this->section('content') ?>
<div class="relative min-h-full xl:h-full xl:min-h-0" data-pos-root data-pos-policy="<?= esc(json_encode([
    'itemDiscountMaxAmount' => $policy['item_discount_max_amount_cents'] / 100,
    'itemDiscountMaxPercent' => $policy['item_discount_max_percent_hundredths'] / 100,
    'transactionDiscountMaxAmount' => $policy['transaction_discount_max_amount_cents'] / 100,
    'transactionDiscountMaxPercent' => $policy['transaction_discount_max_percent_hundredths'] / 100,
    'serviceChargeEnabled' => $policy['service_charge_enabled'],
    'serviceChargeRate' => $policy['service_charge_rate_hundredths'] / 100,
    'taxEnabled' => $policy['tax_enabled'],
    'taxRate' => $policy['tax_rate_hundredths'] / 100,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), 'attr') ?>" data-pos-order="<?= esc(json_encode($activeOrder ? [
    'id' => (int) $activeOrder['id'],
    'order_type' => $activeOrder['order_type'],
    'table_number' => $activeOrder['table_number'],
    'customer_name' => $activeOrder['customer_name'],
    'items' => $activeOrder['items'],
] : null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), 'attr') ?>">
    <?php if (!$shift): ?>
        <div class="grid min-h-[calc(100dvh-10rem)] place-items-center p-4 sm:p-8">
            <section class="w-full max-w-xl border border-status-warning bg-[#FFF7ED] p-6 sm:p-8">
                <p class="section-kicker">Kasir belum siap</p>
                <h1 class="mt-3 font-display text-3xl text-[#78350F]">Buka shift untuk memulai penjualan.</h1>
                <p class="mt-3 leading-7 text-[#78350F]">Catat modal awal terlebih dahulu agar kas laci dapat direkonsiliasi ketika shift ditutup.</p>
                <a class="button-primary mt-6" href="<?= site_url('panel/shifts') ?>">Buka shift</a>
            </section>
        </div>
    <?php elseif ($products === []): ?>
        <div class="grid min-h-[calc(100dvh-10rem)] place-items-center p-4 sm:p-8">
            <section class="panel-card w-full max-w-xl p-6 sm:p-8">
                <p class="section-kicker">Katalog kosong</p>
                <h1 class="mt-3 font-display text-3xl">Belum ada produk aktif.</h1>
                <p class="mt-3 leading-7 text-ink-muted">Owner perlu menerbitkan produk dan varian sebelum transaksi dapat dibuat.</p>
                <?php if (($user['role'] ?? null) === 'owner'): ?><a class="button-primary mt-6" href="<?= site_url('panel/catalog') ?>">Kelola produk</a><?php endif ?>
            </section>
        </div>
    <?php else: ?>
        <div class="xl:grid xl:h-full xl:min-h-0 xl:grid-cols-[minmax(0,1fr)_25rem] xl:overflow-hidden">
            <section class="px-4 pb-28 sm:px-6 xl:h-full xl:min-h-0 xl:overflow-y-auto xl:overscroll-contain xl:px-8 xl:pb-8" aria-labelledby="pos-heading">
                <header class="sticky top-0 z-20 -mx-4 border-b border-border-warm bg-white px-4 py-4 sm:-mx-6 sm:px-6 xl:-mx-8 xl:px-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-3"><h1 id="pos-heading" class="font-display text-2xl sm:text-3xl">Pilih menu</h1><span class="status-label bg-brand-sun text-brand-cocoa"><?= esc($shift['shift_number']) ?></span></div>
                            <p class="mt-1 text-sm text-ink-muted"><?= count($products) ?> varian menu</p>
                        </div>
                        <div class="w-full sm:max-w-md"><label class="sr-only" for="pos-search">Cari produk atau SKU</label><input class="form-input" id="pos-search" type="search" data-pos-search placeholder="Cari menu atau SKU…" autocomplete="off"></div>
                    </div>
                </header>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 2xl:grid-cols-3" data-pos-products>
                    <?php foreach ($products as $product): ?>
                        <article class="panel-card group overflow-hidden" data-pos-product data-search="<?= esc(mb_strtolower($product['product_name'] . ' ' . $product['variant_name'] . ' ' . $product['sku'])) ?>">
                            <div class="grid aspect-[16/7] place-items-center overflow-hidden border-b border-border-warm <?= $product['image_path'] ? 'bg-white' : 'bg-surface-soft-yellow' ?>">
                                <?php if ($product['image_path']): ?><img class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]" src="<?= base_url($product['image_path']) ?>" alt=""><?php else: ?><span class="font-display text-4xl opacity-20" aria-hidden="true"><?= esc(mb_substr($product['product_name'], 0, 1)) ?></span><?php endif ?>
                            </div>
                            <div class="p-4">
                                <p class="text-xs font-bold uppercase tracking-wider text-brand-chili"><?= esc($product['category_name']) ?></p>
                                <div class="mt-1 flex items-start justify-between gap-3"><div><h2 class="font-bold"><?= esc($product['product_name']) ?></h2><p class="mt-1 text-sm text-ink-muted"><?= esc($product['variant_name']) ?> · <?= esc($product['sku']) ?></p></div><p class="shrink-0 font-black tabular-nums">Rp<?= number_format((float) $product['price'], 0, ',', '.') ?></p></div>
                                <p class="mt-3 inline-flex min-h-7 items-center rounded-full px-3 text-xs font-extrabold <?= $product['is_available'] ? 'bg-[#E8F5E9] text-[#1F6E3F]' : 'bg-[#FFF0EB] text-brand-chili' ?>" <?= $product['is_available'] ? 'role="status"' : 'role="alert"' ?>><?= $product['is_available'] ? 'Tersedia' : (($product['availability_status'] === 'sold_out') ? 'Habis' : (($product['availability_status'] === 'locked') ? 'Dikunci opname' : 'Belum siap')) ?></p>
                                <?php if (!$product['is_available']): ?><p class="mt-2 text-sm font-semibold text-brand-chili" id="pos-availability-<?= (int) $product['variant_id'] ?>"><?= esc($product['availability_reason']) ?></p><?php endif ?>
                                <?php if ($toppings !== [] && $product['stock_mode'] === 'recipe'): ?><details class="mt-3 border-t border-border-warm pt-3"><summary class="cursor-pointer text-sm font-bold underline decoration-brand-sun decoration-2 underline-offset-4">Tambah topping</summary><div class="mt-3 space-y-2"><?php foreach ($toppings as $topping): ?><label class="flex min-h-11 items-center justify-between gap-3 border border-border-warm px-3 text-sm <?= $topping['is_available'] ? '' : 'cursor-not-allowed opacity-60' ?>"><span><input type="checkbox" value="<?= (int) $topping['id'] ?>" data-topping data-name="<?= esc($topping['name'], 'attr') ?>" data-price="<?= esc($topping['additional_price'], 'attr') ?>" <?= $topping['is_available'] ? '' : 'disabled' ?>> <?= esc($topping['name']) ?><?php if (!$topping['is_available']): ?><span class="ml-1 font-semibold text-brand-chili">(tidak tersedia)</span><?php endif ?></span><strong>+Rp<?= number_format((float) $topping['additional_price'], 0, ',', '.') ?></strong></label><?php endforeach ?></div></details><?php endif ?>
                                <button class="button-primary mt-4 w-full disabled:cursor-not-allowed disabled:border-[#716356] disabled:bg-[#E7DED2] disabled:text-[#33271E] disabled:opacity-100" type="button" data-add-product data-variant-id="<?= (int) $product['variant_id'] ?>" data-product-name="<?= esc($product['product_name'], 'attr') ?>" data-variant-name="<?= esc($product['variant_name'], 'attr') ?>" data-price="<?= esc($product['price'], 'attr') ?>" <?= $product['is_available'] ? '' : 'disabled aria-describedby="pos-availability-' . (int) $product['variant_id'] . '"' ?>><?= $product['is_available'] ? 'Tambah' : 'Tidak tersedia' ?></button>
                            </div>
                        </article>
                    <?php endforeach ?>
                </div>
                <p class="mt-5 hidden panel-card p-5 text-ink-muted" data-pos-empty>Produk tidak ditemukan.</p>
            </section>

            <div class="fixed inset-0 z-40 hidden bg-brand-cocoa/50 xl:hidden" data-pos-cart-overlay></div>
            <aside class="fixed inset-y-0 right-0 z-50 flex h-dvh w-full max-w-md translate-x-full flex-col border-l border-brand-cocoa bg-surface-white transition-transform duration-200 max-md:top-auto max-md:bottom-0 max-md:h-[min(88dvh,48rem)] max-md:max-w-none max-md:translate-y-full max-md:rounded-t-2xl max-md:border-l-0 max-md:border-t md:translate-y-0 xl:relative xl:z-auto xl:h-full xl:min-h-0 xl:w-auto xl:translate-x-0 xl:translate-y-0 xl:overflow-hidden" data-pos-cart aria-label="Keranjang dan pembayaran" aria-hidden="true" inert>
                <div class="flex shrink-0 items-center justify-between border-b border-brand-cocoa bg-brand-sun px-5 py-4">
                    <div><h2 class="font-display text-xl">Pesanan</h2><p class="mt-1 text-sm" data-cart-count>0 item</p></div>
                    <button class="button-icon xl:hidden" type="button" data-pos-cart-close><span class="sr-only">Tutup keranjang</span><svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
                </div>
                <?php if ($heldOrders !== []): ?>
                    <details class="shrink-0 border-b border-border-warm bg-white px-5 py-3">
                        <summary class="min-h-11 cursor-pointer py-3 text-sm font-bold">Pesanan ditahan (<?= count($heldOrders) ?>)</summary>
                        <div class="max-h-56 space-y-3 overflow-y-auto pb-2">
                            <?php foreach ($heldOrders as $heldOrder): ?>
                                <?php $heldType = ['dine_in' => 'Makan di tempat', 'takeaway' => 'Bawa pulang'][$heldOrder['order_type']] ?? $heldOrder['order_type']; ?>
                                <article class="border border-border-warm p-3">
                                    <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold text-ink-muted"><?= esc($heldOrder['order_number']) ?></p><p class="mt-1 text-sm font-bold"><?= esc($heldType) ?><?= $heldOrder['table_number'] ? ' · Meja ' . esc($heldOrder['table_number']) : '' ?></p><p class="mt-1 text-xs text-ink-muted"><?= esc($heldOrder['customer_name'] ?: count($heldOrder['items']) . ' baris pesanan') ?></p></div><span class="status-label bg-surface-soft-yellow text-brand-cocoa"><?= array_sum(array_map(static fn (array $item): int => (int) $item['quantity'], $heldOrder['items'])) ?> item</span></div>
                                    <div class="mt-3 grid grid-cols-2 gap-2"><a class="button-primary min-h-11 text-sm" href="<?= site_url('panel/pos?order=' . (int) $heldOrder['id']) ?>">Lanjutkan</a><form action="<?= site_url('panel/pos/orders/' . (int) $heldOrder['id'] . '/cancel') ?>" method="post" data-confirm-form="Batalkan <?= esc($heldOrder['order_number']) ?>? Pesanan ini belum dibayar dan tidak akan membuat penjualan atau memotong stok."><?= csrf_field() ?><button class="button-secondary min-h-11 w-full border-status-danger text-status-danger" type="submit">Batalkan</button></form></div>
                                </article>
                            <?php endforeach ?>
                        </div>
                    </details>
                <?php endif ?>
                <div class="min-h-24 flex-1 divide-y divide-border-warm overflow-y-auto overscroll-contain" data-cart-items><p class="p-5 text-sm text-ink-muted">Belum ada produk di keranjang.</p></div>
                <form action="<?= site_url('panel/pos/finalize') ?>" method="post" class="max-h-[62dvh] shrink-0 overflow-y-auto overscroll-contain border-t border-brand-cocoa bg-surface-cream p-5 xl:max-h-[65%]" data-pos-checkout>
                    <?= csrf_field() ?><input type="hidden" name="idempotency_key" value="<?= esc($idempotencyKey) ?>"><input type="hidden" name="pos_order_id" value="<?= esc((string) ($activeOrder['id'] ?? '')) ?>" data-pos-order-id><input type="hidden" name="cart_payload" data-cart-payload><input type="hidden" name="payment_payload" data-payment-payload><input type="hidden" name="transaction_discount_payload" data-transaction-discount-payload>
                    <div class="mb-4 grid gap-3 border-b border-border-warm pb-4 sm:grid-cols-2">
                        <div class="sm:col-span-2"><label class="form-label" for="order-type">Jenis pesanan</label><select class="form-input" id="order-type" name="order_type" data-order-type><option value="takeaway">Bawa pulang</option><option value="dine_in">Makan di tempat</option></select></div>
                        <div class="hidden" data-table-field><label class="form-label" for="table-number">Nomor meja <span class="font-normal text-ink-muted">(opsional)</span></label><input class="form-input" id="table-number" name="table_number" maxlength="30" data-table-number></div>
                        <div><label class="form-label" for="customer-name">Nama pelanggan <span class="font-normal text-ink-muted">(opsional)</span></label><input class="form-input" id="customer-name" name="customer_name" maxlength="120" data-customer-name></div>
                    </div>
                    <div class="grid grid-cols-[minmax(0,1fr)_7rem] gap-2"><label class="form-label col-span-2" for="transaction-discount-type">Diskon transaksi</label><select class="form-input" id="transaction-discount-type" data-transaction-discount-type><option value="amount">Nominal</option><option value="percent">Persen</option></select><input class="form-input" id="transaction-discount-value" type="number" min="0" step="1" value="0" data-transaction-discount-value aria-label="Nilai diskon transaksi"></div>
                    <div class="receipt-rule my-4"></div><div class="space-y-2 text-sm"><div class="flex justify-between"><span>Subtotal</span><strong data-pos-subtotal>Rp0</strong></div><div class="flex justify-between"><span>Diskon item</span><strong data-pos-item-discount-label>Rp0</strong></div><div class="flex justify-between"><span>Diskon transaksi</span><strong data-pos-transaction-discount-label>Rp0</strong></div><?php if ($policy['service_charge_enabled']): ?><div class="flex justify-between"><span>Biaya layanan (<?= number_format($policy['service_charge_rate_hundredths'] / 100, 2, ',', '.') ?>%)</span><strong data-pos-service-charge>Rp0</strong></div><?php endif ?><?php if ($policy['tax_enabled']): ?><div class="flex justify-between"><span>Pajak (<?= number_format($policy['tax_rate_hundredths'] / 100, 2, ',', '.') ?>%)</span><strong data-pos-tax>Rp0</strong></div><?php endif ?><div class="flex justify-between text-lg"><span class="font-bold">Total bayar</span><strong class="tabular-nums" data-pos-total>Rp0</strong></div></div>
                    <div class="mt-4 hidden border border-status-warning bg-[#FFF7ED] p-3" data-discount-approval><p class="text-sm font-bold text-[#78350F]" role="status">Diskon melewati batas kasir. Minta owner menyetujui transaksi ini.</p><div class="mt-3 space-y-3"><div><label class="form-label" for="discount-approval-identity">Username atau email owner</label><input class="form-input" id="discount-approval-identity" name="discount_approval_identity" autocomplete="username" data-discount-approval-identity></div><div><label class="form-label" for="discount-approval-password">Kata sandi owner</label><input class="form-input" id="discount-approval-password" name="discount_approval_password" type="password" autocomplete="current-password" data-discount-approval-password></div></div></div>
                    <div class="mt-4"><label class="form-label" for="payment-method-1">Metode pembayaran</label><select class="form-input" id="payment-method-1" data-payment-method="1"><option value="cash">Tunai</option><option value="qris">QRIS</option><option value="bank_transfer">Transfer bank</option><option value="debit">Kartu debit</option><option value="credit">Kartu kredit</option></select></div>
                    <div class="mt-3" data-payment-amount-wrap="1"><label class="form-label" for="payment-amount-1">Alokasi pembayaran</label><input class="form-input" id="payment-amount-1" type="number" min="0" step="1" data-payment-amount="1" readonly></div>
                    <div class="mt-3" data-cash-wrap="1"><label class="form-label" for="cash-received-1">Tunai diterima</label><input class="form-input" id="cash-received-1" type="number" min="0" step="1" data-cash-received="1"></div>
                    <div class="mt-3 hidden" data-reference-wrap="1"><label class="form-label" for="payment-reference-1">Nomor referensi</label><input class="form-input" id="payment-reference-1" data-payment-reference="1"></div>
                    <label class="mt-4 flex min-h-11 items-center gap-3 text-sm font-bold"><input class="h-5 w-5" type="checkbox" data-split-payment> Gunakan pembayaran gabungan</label>
                    <div class="mt-4 hidden space-y-3 border-t border-border-warm pt-4" data-payment-two><div><label class="form-label" for="payment-method-2">Metode kedua</label><select class="form-input" id="payment-method-2" data-payment-method="2"><option value="qris">QRIS</option><option value="cash">Tunai</option><option value="bank_transfer">Transfer bank</option><option value="debit">Kartu debit</option><option value="credit">Kartu kredit</option></select></div><div><label class="form-label" for="payment-amount-2">Alokasi kedua</label><input class="form-input" id="payment-amount-2" type="number" min="0" step="1" data-payment-amount="2" readonly></div><div class="hidden" data-cash-wrap="2"><label class="form-label" for="cash-received-2">Tunai diterima</label><input class="form-input" id="cash-received-2" type="number" min="0" step="1" data-cash-received="2"></div><div data-reference-wrap="2"><label class="form-label" for="payment-reference-2">Nomor referensi</label><input class="form-input" id="payment-reference-2" data-payment-reference="2"></div></div>
                    <p class="mt-4 hidden text-sm font-bold text-status-danger" data-pos-error role="alert"></p><button class="button-secondary mt-5 w-full" type="submit" formaction="<?= site_url('panel/pos/orders') ?>" formnovalidate data-hold-submit><?= $activeOrder ? 'Perbarui pesanan ditahan' : 'Simpan sebagai pesanan ditahan' ?></button><button class="button-primary mt-3 w-full" type="submit" data-pay-button disabled>Bayar sekarang</button>
                </form>
            </aside>

            <button class="fixed bottom-4 left-4 right-4 z-30 flex min-h-14 items-center justify-between border border-brand-cocoa bg-brand-sun px-5 font-bold shadow-[5px_5px_0_#2B1D16] xl:hidden" type="button" data-pos-cart-open aria-expanded="false"><span>Keranjang</span><span data-cart-mobile-count>0 item</span></button>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
