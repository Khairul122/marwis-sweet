<?php

/**
 * Helper tampilan publik (landing dan halaman menu).
 */

if (!function_exists('sf_rp')) {
    function sf_rp($value): string
    {
        return 'Rp' . number_format((float) $value, 0, ',', '.');
    }
}

if (!function_exists('sf_stars')) {
    function sf_stars(int $n): string
    {
        $n = max(0, min(5, $n));

        return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
    }
}

if (!function_exists('sf_icon')) {
    /** Ikon resmi Lucide (lucide-static v0.460.0, ISC). */
    function sf_icon(string $name, string $class = 'h-6 w-6'): string
    {
        static $paths = [
            'flame' => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
            'cookie' => '<path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/>',
            'receipt-text' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M14 8H8"/><path d="M16 12H8"/><path d="M13 16H8"/>',
            'map-pin' => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'shopping-bag' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
            'menu' => '<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>',
            'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
            'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
            'arrow-left' => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
            'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
            'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
            'image' => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
            'message-square' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        ];

        return '<svg aria-hidden="true" viewBox="0 0 24 24" class="' . $class . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>';
    }
}

if (!function_exists('sf_wa_icon')) {
    /** Logo WhatsApp resmi (Simple Icons v13.21.0, CC0). */
    function sf_wa_icon(string $class = 'h-5 w-5'): string
    {
        return '<svg aria-hidden="true" viewBox="0 0 24 24" class="' . $class . '" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>';
    }
}

if (!function_exists('sf_variant_picker')) {
    /** Pilihan varian berbentuk radio; dibaca landing.js lewat [data-price] dan [data-add] di dalam [data-card]. */
    function sf_variant_picker(array $product, array $variants, string $scope = 'menu', bool $withPrice = false): string
    {
        if ($variants === []) {
            return '<p class="text-sm font-semibold text-ink-muted">Harga belum tersedia</p>';
        }
        $firstAvailable = null;
        foreach ($variants as $index => $variant) {
            if (!empty($variant['is_available'])) {
                $firstAvailable = $index;
                break;
            }
        }
        $html = '<fieldset class="min-w-0"><legend class="sr-only">Pilih varian ' . esc($product['name']) . '</legend><div class="flex flex-wrap gap-2">';
        foreach ($variants as $i => $v) {
            $id = $scope . '-' . $product['id'] . '-' . $v['id'];
            $available = !empty($v['is_available']);
            $status = $available ? 'Tersedia' : (($v['availability_status'] ?? '') === 'sold_out' ? 'Habis' : 'Tidak tersedia');
            $stateClass = $available ? 'bg-[#E8F5E9] text-[#1F6E3F]' : 'bg-[#FFF0EB] text-brand-chili';
            $html .= '<label class="' . ($available ? 'cursor-pointer' : 'cursor-not-allowed opacity-75') . '" for="' . $id . '"><input class="peer sr-only" type="radio" id="' . $id . '" name="variant-' . $scope . '-' . $product['id'] . '" value="' . (int) $v['id'] . '" data-price="' . (float) $v['price'] . '"' . ($available ? '' : ' disabled') . ($i === $firstAvailable ? ' checked' : '') . '>'
                . '<span class="flex flex-col items-start gap-1 rounded-2xl border border-border-warm bg-white font-bold transition peer-checked:border-brand-cocoa peer-checked:bg-brand-cocoa peer-checked:text-white peer-focus-visible:ring-3 peer-focus-visible:ring-brand-sun peer-focus-visible:ring-offset-2 ' . ($withPrice ? 'min-h-16 px-4 py-2 text-base' : 'min-h-14 px-3.5 py-2 text-sm') . '">'
                . '<span class="flex items-center gap-2">' . esc($v['name']) . ($withPrice ? '<span class="font-semibold tabular-nums opacity-75">' . sf_rp($v['price']) . '</span>' : '') . '</span>'
                . '<span class="rounded-full px-2 py-0.5 text-[11px] font-extrabold leading-tight ' . $stateClass . ' peer-checked:border peer-checked:border-white/50 peer-checked:bg-white peer-checked:text-brand-cocoa">' . $status . '</span></span></label>';
        }

        return $html . '</div></fieldset>';
    }
}

if (!function_exists('sf_topping_image')) {
    /**
     * Foto topping dicocokkan dari nama karena tabel toppings tidak punya kolom gambar.
     * ponytail: pemetaan kata kunci; tambah kolom image_path + upload di panel kalau jenis topping makin banyak.
     */
    function sf_topping_image(string $name): ?string
    {
        $map = ['cokelat' => ['cokelat', 'coklat', 'meses', 'choco'], 'keju' => ['keju', 'cheese'], 'kacang' => ['kacang', 'peanut'], 'susu' => ['susu', 'kental', 'milk']];
        $lower = mb_strtolower($name);
        foreach ($map as $file => $words) {
            foreach ($words as $word) {
                if (str_contains($lower, $word)) {
                    return base_url('assets/img/topping/' . $file . '.webp');
                }
            }
        }

        return null;
    }
}

if (!function_exists('sf_topping_cards')) {
    /** Kartu topping bergambar untuk landing dan halaman menu. */
    function sf_topping_cards(array $toppings, string $grid = 'grid-cols-2 sm:grid-cols-4'): string
    {
        $html = '<ul class="grid gap-4 ' . $grid . '" aria-label="Daftar topping">';
        foreach ($toppings as $topping) {
            $image = sf_topping_image($topping['name']);
            $visual = $image !== null
                ? '<img class="h-24 w-24 rounded-full object-cover shadow-[0_12px_24px_-10px_rgb(45_27_18/.55)] transition duration-300 group-hover:rotate-12 group-hover:scale-105 sm:h-28 sm:w-28" src="' . esc($image) . '" alt="" loading="lazy" width="112" height="112">'
                : '<span class="grid h-24 w-24 place-items-center rounded-full bg-surface-soft-yellow text-brand-sun-hover ring-8 ring-white sm:h-28 sm:w-28">' . sf_icon('cookie', 'h-10 w-10') . '</span>';
            $availability = !empty($topping['is_available'])
                ? '<span class="mt-1.5 text-xs font-semibold text-[#1F6E3F]">Tersedia</span>'
                : '<span class="mt-1.5 text-xs font-semibold text-brand-chili">Tidak tersedia saat ini</span>';
            $html .= '<li class="group flex flex-col items-center rounded-3xl bg-white px-4 pb-5 pt-5 text-center shadow-[0_18px_40px_-24px_rgb(45_27_18/.45)]">'
                . $visual
                . '<span class="mt-4 font-brand text-lg font-bold leading-tight sm:text-xl">' . esc($topping['name']) . '</span>'
                . '<span class="mt-2 rounded-full bg-brand-cocoa px-3.5 py-1 text-sm font-bold tabular-nums text-white">+' . sf_rp($topping['additional_price']) . '</span>'
                . '<span class="mt-1.5 text-xs font-semibold text-ink-muted">per porsi</span>' . $availability
                . '</li>';
        }

        return $html . '</ul>';
    }
}
