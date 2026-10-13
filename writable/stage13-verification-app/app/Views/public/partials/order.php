    <!-- Katalog lengkap untuk keranjang: landing.js membuang item yang varian-nya tidak ada di sini -->
    <script type="application/json" id="catalog-data"><?= json_encode($catalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

    <!-- Bilah pesanan di ponsel -->
    <div class="fixed inset-x-0 bottom-0 z-30 p-3 sm:hidden" data-order-bar hidden>
        <button class="lp-btn lp-btn-dark w-full justify-between shadow-2xl" type="button" data-order-open><span class="flex items-center gap-2"><?= sf_icon('shopping-bag', 'h-5 w-5') ?><span>Lihat pesanan (<span data-order-count>0</span>)</span></span><span class="tabular-nums" data-order-total>Rp0</span></button>
    </div>

    <!-- Laci pesanan -->
    <dialog class="order-drawer" data-order-dialog aria-labelledby="order-title">
        <div class="flex h-full flex-col">
            <header class="flex shrink-0 items-center justify-between gap-3 border-b border-border-warm px-4 py-3 sm:px-5 sm:py-4">
                <h2 class="font-brand text-2xl font-extrabold" id="order-title">Pesanan kamu</h2>
                <button class="lp-btn lp-btn-light !min-h-11 !px-4" type="button" data-order-close>Tutup</button>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3 sm:px-5 sm:py-4">
                <p class="rounded-2xl border-2 border-dashed border-border-warm p-5 text-ink-muted" data-order-empty>Belum ada menu. Pilih rasa di daftar menu, lalu tekan Tambah.</p>
                <ul class="space-y-0" data-order-lines></ul>
            </div>
            <div class="order-drawer-footer shrink-0 space-y-3 border-t border-border-warm bg-white px-4 py-3 sm:space-y-4 sm:px-5 sm:py-4" data-order-footer hidden>
                <details class="order-details rounded-xl border border-border-warm px-3">
                    <summary class="flex min-h-11 cursor-pointer items-center text-sm font-bold">Tambah nama atau catatan (opsional)</summary>
                    <div class="grid gap-3 pb-3 sm:grid-cols-2">
                        <div><label class="form-label" for="order-name">Atas nama</label><input class="form-input" id="order-name" maxlength="60" autocomplete="name"></div>
                        <div><label class="form-label" for="order-note">Catatan</label><input class="form-input" id="order-note" maxlength="120"></div>
                    </div>
                </details>
                <?php if ($waNumber !== ''): ?>
                    <a class="lp-btn lp-btn-sun w-full !justify-between" href="#" data-order-send target="_blank" rel="noopener"><span class="flex items-center gap-2"><?= sf_wa_icon() ?>Kirim lewat WhatsApp</span><strong class="font-bold tabular-nums" data-order-sum>Rp0</strong></a>
                    <div class="grid grid-cols-2 gap-2">
                        <button class="lp-btn lp-btn-light w-full" type="button" data-order-copy>Salin pesanan</button>
                        <button class="lp-btn lp-btn-light w-full" type="button" data-order-clear>Kosongkan</button>
                    </div>
                <?php else: ?>
                    <button class="lp-btn lp-btn-sun w-full !justify-between" type="button" data-order-copy><span>Salin pesanan</span><strong class="font-bold tabular-nums" data-order-sum>Rp0</strong></button>
                    <button class="min-h-11 w-full text-xs font-bold text-ink-muted underline underline-offset-4" type="button" data-order-clear>Kosongkan pesanan</button>
                <?php endif ?>
                <p class="text-sm font-semibold" role="status" data-order-status hidden></p>
            </div>
        </div>
    </dialog>
