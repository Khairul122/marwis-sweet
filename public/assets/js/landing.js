(() => {
    const dataNode = document.getElementById('catalog-data');
    if (!dataNode) return;
    const catalog = JSON.parse(dataNode.textContent);
    const STORE = 'marwis_order_v1';
    const rupiah = (value) => 'Rp' + Math.round(value).toLocaleString('id-ID');

    const variants = new Map();
    catalog.products.forEach((product) => product.variants.forEach((variant) => variants.set(variant.id, { ...variant, product: product.name })));
    const toppingById = new Map(catalog.toppings.map((topping) => [topping.id, topping]));

    // ---- pesanan: [{variantId, qty, toppings: [id]}]
    let lines = [];
    const expandedToppingLines = new Set();
    try {
        const saved = JSON.parse(window.localStorage.getItem(STORE) || '[]');
        lines = saved
            .filter((line) => variants.has(line.variantId) && variants.get(line.variantId).available !== false)
            .map((line) => ({
                variantId: line.variantId,
                qty: Math.min(99, Math.max(1, Number(line.qty) || 1)),
                toppings: (line.toppings || []).filter((id) => toppingById.has(id) && toppingById.get(id).available !== false),
            }));
    } catch { lines = []; }
    const persist = () => { try { window.localStorage.setItem(STORE, JSON.stringify(lines)); } catch { /* penyimpanan peramban ditolak */ } };

    const dialog = document.querySelector('[data-order-dialog]');
    const list = document.querySelector('[data-order-lines]');
    const empty = document.querySelector('[data-order-empty]');
    const footer = document.querySelector('[data-order-footer]');
    const bar = document.querySelector('[data-order-bar]');
    const status = document.querySelector('[data-order-status]');
    const nameInput = document.getElementById('order-name');
    const noteInput = document.getElementById('order-note');
    const sendLink = document.querySelector('[data-order-send]');

    const unitPrice = (line) => variants.get(line.variantId).price + line.toppings.reduce((sum, id) => sum + toppingById.get(id).price, 0);
    const total = () => lines.reduce((sum, line) => sum + unitPrice(line) * line.qty, 0);
    const count = () => lines.reduce((sum, line) => sum + line.qty, 0);

    const message = () => {
        const rows = lines.map((line, index) => {
            const variant = variants.get(line.variantId);
            const extra = line.toppings.length ? ' + ' + line.toppings.map((id) => toppingById.get(id).name).join(', ') : '';
            return `${index + 1}. ${line.qty}x ${variant.product} (${variant.name})${extra}: ${rupiah(unitPrice(line) * line.qty)}`;
        });
        const parts = [`Halo ${catalog.business}, saya mau pesan:`, ...rows, `Total: ${rupiah(total())}`];
        if (nameInput.value.trim()) parts.push(`Atas nama: ${nameInput.value.trim()}`);
        if (noteInput.value.trim()) parts.push(`Catatan: ${noteInput.value.trim()}`);
        return parts.join('\n');
    };

    const say = (text) => {
        if (!status) return;
        status.textContent = text;
        status.hidden = !text;
    };

    const render = () => {
        document.querySelectorAll('[data-order-count]').forEach((node) => { node.textContent = String(count()); });
        document.querySelectorAll('[data-order-total], [data-order-sum]').forEach((node) => { node.textContent = rupiah(total()); });
        if (bar) bar.hidden = lines.length === 0;
        empty.hidden = lines.length > 0;
        footer.hidden = lines.length === 0;
        list.replaceChildren(...lines.map((line, index) => {
            const variant = variants.get(line.variantId);
            const item = document.createElement('li');
            item.className = 'border-b border-border-warm py-3 last:border-b-0';
            const head = document.createElement('div');
            head.className = 'flex items-start justify-between gap-3';
            head.innerHTML = '<div class="min-w-0"><p class="text-sm font-bold leading-5"></p><p class="mt-0.5 text-xs text-ink-muted"></p></div><p class="shrink-0 text-sm font-bold tabular-nums"></p>';
            head.querySelector('p.font-bold').textContent = variant.product;
            head.querySelector('p.text-xs').textContent = `${variant.name} · ${rupiah(unitPrice(line))} / porsi`;
            head.querySelector('p.tabular-nums').textContent = rupiah(unitPrice(line) * line.qty);
            item.append(head);

            if (variant.recipe && catalog.toppings.length) {
                const toppingDetails = document.createElement('details');
                toppingDetails.className = 'mt-2 border-t border-dashed border-border-warm pt-1';
                toppingDetails.open = expandedToppingLines.has(line);
                toppingDetails.addEventListener('toggle', () => {
                    if (toppingDetails.open) expandedToppingLines.add(line);
                    else expandedToppingLines.delete(line);
                });
                const toppingSummary = document.createElement('summary');
                toppingSummary.className = 'flex min-h-11 cursor-pointer items-center text-xs font-bold text-ink-muted';
                toppingSummary.textContent = line.toppings.length
                    ? `${line.toppings.length} topping dipilih · ${line.toppings.map((id) => toppingById.get(id).name).join(', ')}`
                    : 'Tambah topping (opsional)';
                const group = document.createElement('fieldset');
                group.className = 'grid grid-cols-2 gap-2 pb-2';
                const legend = document.createElement('legend');
                legend.className = 'col-span-2 pb-2 text-xs font-bold text-ink-muted';
                legend.textContent = 'Pilih topping';
                const grid = document.createElement('div');
                grid.className = 'col-span-2 grid grid-cols-2 gap-2';
                group.append(legend, grid);
                catalog.toppings.forEach((topping) => {
                    const available = topping.available !== false;
                    const on = line.toppings.includes(topping.id);
                    const label = document.createElement('label');
                    label.className = available ? 'cursor-pointer' : 'cursor-not-allowed opacity-60';
                    const box = document.createElement('input');
                    box.type = 'checkbox';
                    box.className = 'peer sr-only';
                    box.checked = on;
                    box.disabled = !available;
                    box.setAttribute('aria-label', available
                        ? `${topping.name} untuk ${variant.product}, tambah ${rupiah(topping.price)}`
                        : `${topping.name} untuk ${variant.product}, tidak tersedia saat ini`);
                    box.addEventListener('change', () => {
                        line.toppings = box.checked ? [...line.toppings, topping.id] : line.toppings.filter((id) => id !== topping.id);
                        persist();
                        render();
                        list.querySelectorAll('li')[index]?.querySelectorAll('input[type="checkbox"]')[catalog.toppings.indexOf(topping)]?.focus();
                    });
                    // Tile dibangun ulang tiap render, jadi status terpilih cukup ditulis langsung ke class
                    const tile = document.createElement('span');
                    tile.className = `flex min-h-14 items-center gap-2.5 rounded-2xl border p-1.5 pr-2.5 transition peer-focus-visible:ring-3 peer-focus-visible:ring-brand-sun peer-focus-visible:ring-offset-2 ${on ? 'border-brand-cocoa bg-brand-sun/30' : 'border-border-warm bg-white hover:border-ink-muted'} ${available ? '' : 'bg-surface-cream'}`;
                    const visual = topping.image ? document.createElement('img') : document.createElement('span');
                    if (topping.image) {
                        visual.src = topping.image;
                        visual.alt = '';
                        visual.className = 'h-10 w-10 shrink-0 rounded-full object-cover';
                    } else {
                        visual.className = 'grid h-10 w-10 shrink-0 place-items-center rounded-full bg-surface-soft-yellow text-lg font-bold text-brand-sun-hover';
                        visual.textContent = '+';
                    }
                    const text = document.createElement('span');
                    text.className = 'min-w-0 flex-1 leading-tight';
                    const name = document.createElement('span');
                    name.className = 'block text-sm font-bold';
                    name.textContent = topping.name;
                    const price = document.createElement('span');
                    price.className = 'block text-xs font-semibold tabular-nums text-ink-muted';
                    price.textContent = `+${rupiah(topping.price)}`;
                    text.append(name, price);
                    if (!available) {
                        const stock = document.createElement('span');
                        stock.className = 'mt-0.5 block text-[11px] font-semibold text-brand-chili';
                        stock.textContent = 'Tidak tersedia saat ini';
                        text.append(stock);
                    }
                    const tick = document.createElement('span');
                    tick.className = `grid h-5 w-5 shrink-0 place-items-center rounded-full border-2 text-[11px] font-black ${on ? 'border-brand-cocoa bg-brand-cocoa text-white' : 'border-border-warm text-transparent'}`;
                    tick.setAttribute('aria-hidden', 'true');
                    tick.textContent = '✓';
                    tile.append(visual, text, tick);
                    label.append(box, tile);
                    grid.append(label);
                });
                toppingDetails.append(toppingSummary, group);
                item.append(toppingDetails);
            }

            const controls = document.createElement('div');
            controls.className = 'mt-2 flex items-center justify-between gap-3';
            const stepper = document.createElement('div');
            stepper.className = 'flex items-center overflow-hidden rounded-lg border border-border-warm';
            const button = (label, aria, handler) => {
                const node = document.createElement('button');
                node.type = 'button';
                node.className = 'h-11 w-11 text-lg font-bold hover:bg-surface-soft-yellow';
                node.textContent = label;
                node.setAttribute('aria-label', `${aria} ${variant.product}`);
                node.addEventListener('click', handler);
                return node;
            };
            const qty = document.createElement('span');
            qty.className = 'w-8 text-center text-sm font-bold tabular-nums';
            qty.textContent = String(line.qty);
            qty.setAttribute('aria-live', 'polite');
            stepper.append(button('−', 'Kurangi', () => { line.qty = Math.max(1, line.qty - 1); persist(); render(); }), qty, button('+', 'Tambah', () => { line.qty = Math.min(99, line.qty + 1); persist(); render(); }));
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'min-h-11 px-2 text-xs font-bold text-ink-muted underline underline-offset-4';
            remove.textContent = 'Hapus';
            remove.setAttribute('aria-label', `Hapus ${variant.product} dari pesanan`);
            remove.addEventListener('click', () => { expandedToppingLines.delete(line); lines.splice(index, 1); persist(); render(); say(`${variant.product} dihapus dari pesanan.`); });
            controls.append(stepper, remove);
            item.append(controls);
            return item;
        }));
        if (sendLink && lines.length) {
            sendLink.href = `https://wa.me/${catalog.whatsapp}?text=${encodeURIComponent(message())}`;
        }
    };

    const add = (variantId) => {
        const variant = variants.get(variantId);
        if (!variant || variant.available === false) {
            say('Varian ini sedang tidak tersedia.');
            return;
        }
        const existing = lines.find((line) => line.variantId === variantId);
        if (existing) existing.qty = Math.min(99, existing.qty + 1);
        else lines.push({ variantId, qty: 1, toppings: [] });
        persist();
        render();
        say(`${variant.product} (${variant.name}) ditambahkan.`);
    };

    // ---- kartu menu: ganti varian memperbarui harga, tombol tambah memasukkan ke pesanan
    document.addEventListener('change', (event) => {
        const radio = event.target.closest('input[type="radio"][data-price]');
        if (!radio) return;
        const out = radio.closest('[data-card]')?.querySelector('[data-price-out]');
        if (out) out.textContent = rupiah(Number(radio.dataset.price));
    });
    document.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-add]');
        if (addButton) {
            const checked = addButton.closest('[data-card]')?.querySelector('input[type="radio"]:checked');
            if (!checked) return;
            add(Number(checked.value));
            addButton.dataset.label ??= addButton.textContent;
            addButton.textContent = 'Ditambahkan';
            window.setTimeout(() => { addButton.textContent = addButton.dataset.label; }, 1400);
            return;
        }
        if (event.target.closest('[data-order-open]')) { render(); if (dialog && !dialog.open) dialog.showModal(); return; }
        if (event.target.closest('[data-order-close]')) { dialog?.close(); return; }
        if (event.target === dialog) dialog.close();
    });

    // ---- aksi pesanan
    [nameInput, noteInput].forEach((input) => input.addEventListener('input', render));
    document.querySelector('[data-order-clear]')?.addEventListener('click', () => { lines = []; persist(); render(); say('Pesanan dikosongkan.'); });
    document.querySelector('[data-order-copy]')?.addEventListener('click', async () => {
        const text = message();
        try {
            await navigator.clipboard.writeText(text);
            say('Pesanan disalin. Tempel ke chat atau tunjukkan ke kasir.');
        } catch {
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            (dialog || document.body).append(area);
            area.select();
            const ok = document.execCommand && document.execCommand('copy');
            area.remove();
            say(ok ? 'Pesanan disalin. Tempel ke chat atau tunjukkan ke kasir.' : 'Penyalinan gagal. Catat pesanan secara manual dari daftar di atas.');
        }
    });

    // ---- galeri halaman menu
    const galleryMain = document.querySelector('[data-gallery-main]');
    document.querySelectorAll('[data-gallery-thumb]').forEach((thumb) => thumb.addEventListener('click', () => {
        if (!galleryMain) return;
        galleryMain.src = thumb.dataset.src;
        galleryMain.alt = thumb.dataset.alt;
        document.querySelectorAll('[data-gallery-thumb]').forEach((other) => {
            const on = other === thumb;
            other.classList.toggle('ring-brand-cocoa', on);
            other.classList.toggle('ring-transparent', !on);
            other.classList.toggle('opacity-70', !on);
            other.setAttribute('aria-pressed', String(on));
        });
    }));

    // ---- muncul saat discroll (sekali, tanpa loop)
    const reveals = [...document.querySelectorAll('.reveal')];
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
            if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); }
        }), { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
        reveals.forEach((node) => observer.observe(node));
    } else {
        reveals.forEach((node) => node.classList.add('is-visible'));
    }

    render();
})();
