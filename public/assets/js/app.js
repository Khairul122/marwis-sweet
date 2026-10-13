(() => {
    const menuToggle = document.querySelector('[data-menu-toggle]');
    const mobileMenu = document.querySelector('[data-menu-mobile]');
    if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', () => {
            const open = mobileMenu.classList.toggle('hidden') === false;
            menuToggle.setAttribute('aria-expanded', String(open));
        });
        mobileMenu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
            mobileMenu.classList.add('hidden');
            menuToggle.setAttribute('aria-expanded', 'false');
        }));
    }

    const drawer = document.querySelector('[data-drawer]');
    const drawerOverlay = document.querySelector('[data-drawer-overlay]');
    const drawerOpen = document.querySelector('[data-drawer-open]');
    const drawerClose = document.querySelector('[data-drawer-close]');
    let drawerReturnFocus = null;
    const closeDrawer = () => {
        if (!drawer || !drawerOverlay) return;
        drawer.classList.add('-translate-x-full');
        drawerOverlay.classList.add('hidden');
        drawerOpen?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden');
        drawerReturnFocus?.focus();
    };
    const openDrawer = () => {
        if (!drawer || !drawerOverlay) return;
        drawerReturnFocus = document.activeElement;
        drawer.classList.remove('-translate-x-full');
        drawerOverlay.classList.remove('hidden');
        drawerOpen?.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
        drawerClose?.focus();
    };
    drawerOpen?.addEventListener('click', openDrawer);
    drawerClose?.addEventListener('click', closeDrawer);
    drawerOverlay?.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && drawer && !drawer.classList.contains('-translate-x-full')) closeDrawer();
    });

    const filterButtons = [...document.querySelectorAll('[data-category-filter]')];
    const productCards = [...document.querySelectorAll('[data-product-card]')];
    const emptyFilter = document.querySelector('[data-filter-empty]');
    const productSearch = document.querySelector('[data-product-search]');
    let activeCategory = 'all';
    const filterProducts = () => {
        const query = productSearch?.value.trim().toLocaleLowerCase('id') ?? '';
        let visible = 0;
        productCards.forEach((card) => {
            const categoryMatches = activeCategory === 'all' || card.dataset.category === activeCategory;
            const searchMatches = query === '' || card.dataset.search.includes(query);
            const show = categoryMatches && searchMatches;
            card.classList.toggle('hidden', !show);
            if (show) visible += 1;
        });
        emptyFilter?.classList.toggle('hidden', visible > 0);
    };
    filterButtons.forEach((button) => button.addEventListener('click', () => {
        activeCategory = button.dataset.categoryFilter;
        filterButtons.forEach((item) => {
            const active = item === button;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-pressed', String(active));
        });
        filterProducts();
    }));
    productSearch?.addEventListener('input', filterProducts);

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.textContent = reveal ? 'Sembunyikan' : 'Tampilkan';
            button.setAttribute('aria-pressed', String(reveal));
        });
    });

    document.querySelectorAll('[data-image-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            const wrap = document.querySelector('[data-image-preview-wrap]');
            if (!file || !wrap) return;
            const source = URL.createObjectURL(file);
            wrap.classList.remove('hidden');
            wrap.querySelectorAll('[data-image-preview]').forEach((image) => { image.src = source; });
        });
    });

    document.querySelectorAll('[data-carousel]').forEach((carousel) => {
        const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
        const status = carousel.querySelector('[data-carousel-status]');
        let active = 0;
        const show = (index) => {
            active = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => slide.classList.toggle('hidden', slideIndex !== active));
            if (status) status.textContent = `${active + 1} / ${slides.length}`;
        };
        carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => show(active - 1));
        carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => show(active + 1));
    });

    document.querySelectorAll('table[data-datatable]').forEach((table) => {
        const rows = [...table.tBodies[0].rows].filter((row) => !row.querySelector('[colspan]'));
        if (rows.length === 0) return;
        const holder = table.parentElement;
        const bar = document.createElement('div');
        bar.className = 'flex flex-wrap items-center justify-between gap-3 border-b border-border-warm px-5 py-3 text-sm';
        bar.innerHTML = '<label class="flex items-center gap-2">Tampilkan <select class="form-input !min-h-9 !w-auto !py-1" data-dt-size><option>10</option><option>25</option><option>50</option><option>100</option></select> data</label><label class="flex items-center gap-2">Cari: <input type="search" class="form-input !min-h-9 !w-48 !py-1" data-dt-search></label>';
        const foot = document.createElement('div');
        foot.className = 'flex flex-wrap items-center justify-between gap-3 border-t border-border-warm px-5 py-3 text-sm';
        foot.innerHTML = '<p data-dt-info role="status"></p><div class="flex flex-wrap gap-1" data-dt-pager></div>';
        holder.before(bar);
        holder.after(foot);
        const size = bar.querySelector('[data-dt-size]');
        const search = bar.querySelector('[data-dt-search]');
        const info = foot.querySelector('[data-dt-info]');
        const pager = foot.querySelector('[data-dt-pager]');
        let page = 1;
        const render = () => {
            const q = search.value.trim().toLowerCase();
            const per = Number(size.value);
            const hits = rows.filter((row) => row.textContent.toLowerCase().includes(q));
            const pages = Math.max(1, Math.ceil(hits.length / per));
            page = Math.min(page, pages);
            const start = (page - 1) * per;
            rows.forEach((row) => { row.hidden = true; });
            hits.slice(start, start + per).forEach((row, i) => {
                row.hidden = false;
                const number = row.querySelector('[data-dt-no]');
                if (number) number.textContent = String(start + i + 1);
            });
            info.textContent = hits.length === 0 ? 'Tidak ada data yang cocok' : `Menampilkan ${start + 1} sampai ${Math.min(start + per, hits.length)} dari ${hits.length} data`;
            pager.replaceChildren();
            const add = (label, target, disabled, current) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = label;
                button.disabled = disabled;
                button.className = 'filter-chip !min-h-9 !px-3' + (current ? ' is-active' : '');
                if (current) button.setAttribute('aria-current', 'page');
                button.addEventListener('click', () => { page = target; render(); });
                pager.append(button);
            };
            add('Sebelumnya', page - 1, page === 1, false);
            for (let p = 1; p <= pages; p += 1) if (p === 1 || p === pages || Math.abs(p - page) <= 1) add(String(p), p, false, p === page);
            add('Berikutnya', page + 1, page === pages, false);
        };
        size.addEventListener('change', () => { page = 1; render(); });
        search.addEventListener('input', () => { page = 1; render(); });
        render();
    });

    const flashDialog = document.querySelector('[data-flash-dialog]');
    if (flashDialog && typeof flashDialog.showModal === 'function') {
        flashDialog.close();
        flashDialog.showModal();
    }
    document.querySelectorAll('dialog [data-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog').close());
    });
    document.querySelectorAll('dialog.app-dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    });

    const confirmDialog = document.querySelector('[data-confirm-dialog]');
    document.querySelectorAll('[data-confirm-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === '1') return;
            event.preventDefault();
            if (!confirmDialog || typeof confirmDialog.showModal !== 'function') {
                if (window.confirm(form.dataset.confirmForm)) { form.dataset.confirmed = '1'; form.requestSubmit(event.submitter); }
                return;
            }
            confirmDialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirmForm;
            const accept = confirmDialog.querySelector('[data-confirm-accept]');
            const onAccept = () => { form.dataset.confirmed = '1'; confirmDialog.close(); form.requestSubmit(event.submitter); };
            accept.addEventListener('click', onAccept, { once: true });
            confirmDialog.addEventListener('close', () => accept.removeEventListener('click', onAccept), { once: true });
            confirmDialog.showModal();
        });
    });

    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.copyText);
                const original = button.textContent;
                button.textContent = 'Tersalin';
                window.setTimeout(() => { button.textContent = original; }, 1800);
            } catch { button.textContent = 'Salin manual dari kotak di atas'; }
        });
    });

    const opnameScopeInputs = [...document.querySelectorAll('[data-opname-scope]')];
    const opnamePartial = document.querySelector('[data-opname-partial]');
    if (opnameScopeInputs.length && opnamePartial) {
        const updateOpnameScope = () => {
            const partial = opnameScopeInputs.some((input) => input.checked && input.value === 'partial');
            opnamePartial.classList.toggle('hidden', !partial);
            opnamePartial.querySelectorAll('input[type="checkbox"]').forEach((input) => { input.disabled = !partial; });
        };
        opnameScopeInputs.forEach((input) => input.addEventListener('change', updateOpnameScope));
        updateOpnameScope();
    }

    const reportPeriod = document.querySelector('[data-report-period]');
    if (reportPeriod) {
        const periodGroups = {
            daily: document.querySelector('[data-report-date]'),
            monthly: document.querySelector('[data-report-month]'),
            yearly: document.querySelector('[data-report-year]'),
        };
        const updateReportPeriod = () => {
            Object.entries(periodGroups).forEach(([type, group]) => {
                if (!group) return;
                const active = type === reportPeriod.value;
                group.classList.toggle('hidden', !active);
                group.querySelectorAll('input').forEach((input) => { input.disabled = !active; input.required = active; });
            });
        };
        reportPeriod.addEventListener('change', updateReportPeriod);
        updateReportPeriod();
    }

    const posRoot = document.querySelector('[data-pos-root]');
    if (posRoot) {
        const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
        const cart = new Map();
        const cartItems = posRoot.querySelector('[data-cart-items]');
        const cartPayload = posRoot.querySelector('[data-cart-payload]');
        const paymentPayload = posRoot.querySelector('[data-payment-payload]');
        const discountInput = posRoot.querySelector('[data-pos-discount]');
        const splitInput = posRoot.querySelector('[data-split-payment]');
        const paymentTwo = posRoot.querySelector('[data-payment-two]');
        const payButton = posRoot.querySelector('[data-pay-button]');
        const checkout = posRoot.querySelector('[data-pos-checkout]');
        const errorBox = posRoot.querySelector('[data-pos-error]');
        const cartPanel = posRoot.querySelector('[data-pos-cart]');
        const cartOverlay = posRoot.querySelector('[data-pos-cart-overlay]');
        const cartOpen = posRoot.querySelector('[data-pos-cart-open]');
        const cartClose = posRoot.querySelector('[data-pos-cart-close]');
        let cartReturnFocus = null;
        let currentTotal = 0;
        const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);

        const getPayment = (number) => ({
            method: posRoot.querySelector(`[data-payment-method="${number}"]`)?.value,
            amount: Number(posRoot.querySelector(`[data-payment-amount="${number}"]`)?.value || 0),
            cash_received: Number(posRoot.querySelector(`[data-cash-received="${number}"]`)?.value || 0),
            reference_number: posRoot.querySelector(`[data-payment-reference="${number}"]`)?.value.trim() || '',
        });

        const updatePaymentFields = (number) => {
            const method = posRoot.querySelector(`[data-payment-method="${number}"]`)?.value;
            posRoot.querySelector(`[data-cash-wrap="${number}"]`)?.classList.toggle('hidden', method !== 'cash');
            posRoot.querySelector(`[data-reference-wrap="${number}"]`)?.classList.toggle('hidden', method === 'cash');
        };

        const updatePayments = () => {
            const amountOne = posRoot.querySelector('[data-payment-amount="1"]');
            const amountTwo = posRoot.querySelector('[data-payment-amount="2"]');
            if (!amountOne || !amountTwo) return;
            if (splitInput?.checked) {
                amountOne.readOnly = false;
                const first = Math.min(Math.max(Number(amountOne.value || 0), 0), currentTotal);
                amountTwo.value = String(Math.max(currentTotal - first, 0));
            } else {
                amountOne.readOnly = true;
                amountOne.value = String(currentTotal);
                amountTwo.value = '0';
            }
            const payments = [getPayment(1)];
            if (splitInput?.checked && Number(amountTwo.value) > 0) payments.push(getPayment(2));
            if (paymentPayload) paymentPayload.value = JSON.stringify(payments);
        };

        const renderCart = () => {
            const entries = [...cart.values()];
            if (cartItems) {
                cartItems.innerHTML = entries.length === 0 ? '<p class="p-5 text-sm text-ink-muted">Belum ada produk di keranjang.</p>' : entries.map((item) => `
                    <article class="p-4" data-cart-key="${item.key}">
                        <div class="flex justify-between gap-3"><div><h3 class="text-sm font-bold">${escapeHtml(item.productName)}</h3><p class="mt-1 text-xs text-ink-muted">${escapeHtml(item.variantName)}${item.toppings.length ? ' · ' + item.toppings.map((top) => escapeHtml(top.name)).join(', ') : ''}</p></div><strong class="text-sm tabular-nums">${rupiah.format((item.price + item.toppings.reduce((sum, top) => sum + top.price, 0)) * item.quantity)}</strong></div>
                        <div class="mt-3 flex items-center gap-2"><button class="button-icon h-9 min-h-9 w-9" type="button" data-cart-decrease aria-label="Kurangi ${escapeHtml(item.productName)}">−</button><span class="w-8 text-center font-bold">${item.quantity}</span><button class="button-icon h-9 min-h-9 w-9" type="button" data-cart-increase aria-label="Tambah ${escapeHtml(item.productName)}">+</button><button class="ml-auto min-h-9 px-2 text-xs font-bold text-status-danger underline" type="button" data-cart-remove>Hapus</button></div>
                    </article>`).join('');
            }
            const subtotal = entries.reduce((sum, item) => sum + (item.price + item.toppings.reduce((topSum, top) => topSum + top.price, 0)) * item.quantity, 0);
            const discount = Math.min(Math.max(Number(discountInput?.value || 0), 0), subtotal);
            currentTotal = subtotal - discount;
            const subtotalLabel = posRoot.querySelector('[data-pos-subtotal]');
            const discountLabel = posRoot.querySelector('[data-pos-discount-label]');
            const totalLabel = posRoot.querySelector('[data-pos-total]');
            const countLabel = posRoot.querySelector('[data-cart-count]');
            const mobileCountLabel = posRoot.querySelector('[data-cart-mobile-count]');
            const itemCount = entries.reduce((sum, item) => sum + item.quantity, 0);
            if (subtotalLabel) subtotalLabel.textContent = rupiah.format(subtotal);
            if (discountLabel) discountLabel.textContent = rupiah.format(discount);
            if (totalLabel) totalLabel.textContent = rupiah.format(currentTotal);
            if (countLabel) countLabel.textContent = `${itemCount} item`;
            if (mobileCountLabel) mobileCountLabel.textContent = `${itemCount} item`;
            if (cartPayload) cartPayload.value = JSON.stringify(entries.map((item) => ({ variant_id: item.variantId, quantity: item.quantity, topping_ids: item.toppings.map((top) => top.id) })));
            if (payButton) payButton.disabled = entries.length === 0 || currentTotal <= 0;
            updatePayments();
        };

        posRoot.querySelectorAll('[data-add-product]').forEach((button) => button.addEventListener('click', () => {
            const card = button.closest('[data-pos-product]');
            const toppings = [...card.querySelectorAll('[data-topping]:checked')].map((input) => ({ id: Number(input.value), name: input.dataset.name, price: Number(input.dataset.price) }));
            const key = `${button.dataset.variantId}:${toppings.map((top) => top.id).sort((a, b) => a - b).join('-')}`;
            const existing = cart.get(key);
            if (existing) existing.quantity += 1;
            else cart.set(key, { key, variantId: Number(button.dataset.variantId), productName: button.dataset.productName, variantName: button.dataset.variantName, price: Number(button.dataset.price), toppings, quantity: 1 });
            renderCart();
        }));

        cartItems?.addEventListener('click', (event) => {
            const action = event.target.closest('button');
            const row = event.target.closest('[data-cart-key]');
            if (!action || !row) return;
            const item = cart.get(row.dataset.cartKey);
            if (!item) return;
            if (action.matches('[data-cart-increase]')) item.quantity += 1;
            if (action.matches('[data-cart-decrease]')) item.quantity = Math.max(1, item.quantity - 1);
            if (action.matches('[data-cart-remove]')) cart.delete(row.dataset.cartKey);
            renderCart();
        });

        const search = posRoot.querySelector('[data-pos-search]');
        search?.addEventListener('input', () => {
            const query = search.value.trim().toLocaleLowerCase('id');
            let visible = 0;
            posRoot.querySelectorAll('[data-pos-product]').forEach((card) => {
                const show = card.dataset.search.includes(query);
                card.classList.toggle('hidden', !show);
                if (show) visible += 1;
            });
            posRoot.querySelector('[data-pos-empty]')?.classList.toggle('hidden', visible > 0);
        });

        discountInput?.addEventListener('input', renderCart);
        splitInput?.addEventListener('change', () => {
            paymentTwo?.classList.toggle('hidden', !splitInput.checked);
            const amountOne = posRoot.querySelector('[data-payment-amount="1"]');
            if (amountOne) amountOne.value = splitInput.checked ? String(Math.floor(currentTotal / 2)) : String(currentTotal);
            updatePayments();
        });
        posRoot.querySelectorAll('[data-payment-method]').forEach((select) => select.addEventListener('change', () => { updatePaymentFields(select.dataset.paymentMethod); updatePayments(); }));
        posRoot.querySelectorAll('[data-payment-amount], [data-cash-received], [data-payment-reference]').forEach((input) => input.addEventListener('input', updatePayments));
        updatePaymentFields(1);
        updatePaymentFields(2);
        renderCart();

        const closeCart = () => {
            if (!cartPanel || !cartOverlay) return;
            cartPanel.classList.add('translate-x-full');
            cartOverlay.classList.add('hidden');
            cartOpen?.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('overflow-hidden');
            cartReturnFocus?.focus();
        };
        const openCart = () => {
            if (!cartPanel || !cartOverlay) return;
            cartReturnFocus = document.activeElement;
            cartPanel.classList.remove('translate-x-full');
            cartOverlay.classList.remove('hidden');
            cartOpen?.setAttribute('aria-expanded', 'true');
            document.body.classList.add('overflow-hidden');
            cartClose?.focus();
        };
        cartOpen?.addEventListener('click', openCart);
        cartClose?.addEventListener('click', closeCart);
        cartOverlay?.addEventListener('click', closeCart);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && cartPanel && !cartPanel.classList.contains('translate-x-full') && window.innerWidth < 1280) closeCart();
        });

        checkout?.addEventListener('submit', (event) => {
            errorBox?.classList.add('hidden');
            updatePayments();
            const payments = JSON.parse(paymentPayload?.value || '[]');
            let message = '';
            if (cart.size === 0) message = 'Tambahkan produk sebelum membayar.';
            else if (Math.abs(payments.reduce((sum, payment) => sum + payment.amount, 0) - currentTotal) > 0.01) message = 'Alokasi pembayaran belum sama dengan total transaksi.';
            else if (payments.some((payment) => payment.method === 'cash' && payment.cash_received < payment.amount)) message = 'Tunai diterima masih kurang.';
            else if (payments.some((payment) => payment.method !== 'cash' && !payment.reference_number)) message = 'Isi nomor referensi pembayaran non-tunai.';
            if (message) {
                event.preventDefault();
                if (errorBox) { errorBox.textContent = message; errorBox.classList.remove('hidden'); }
            } else if (payButton) {
                payButton.disabled = true;
                payButton.textContent = 'Memproses pembayaran...';
            }
        });
    }
})();
