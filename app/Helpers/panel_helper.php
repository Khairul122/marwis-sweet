<?php

/**
 * Komponen tabel panel: satu tempat untuk markup tabel, badge status, dan tombol aksi.
 * Semua argumen berisi teks mentah kecuali sel tabel ($rows) yang sudah berupa HTML (escape di pemanggil).
 */

if (! function_exists('panel_badge')) {
    function panel_badge(bool $active): string
    {
        return $active
            ? '<span class="status-label bg-[#F0FDF4] text-status-success">Aktif</span>'
            : '<span class="status-label bg-surface-soft-yellow text-ink-muted">Nonaktif</span>';
    }
}

if (! function_exists('panel_post')) {
    /** Form POST satu tombol, dengan dialog konfirmasi bila $confirm diisi. */
    function panel_post(string $url, array $fields, string $label, string $class, ?string $confirm = null): string
    {
        $inputs = csrf_field();
        foreach ($fields as $name => $value) {
            $inputs .= '<input type="hidden" name="' . esc($name) . '" value="' . esc((string) $value) . '">';
        }

        return '<form action="' . site_url($url) . '" method="post"' . ($confirm !== null ? ' data-confirm-form="' . esc($confirm) . '"' : '') . '>'
            . $inputs . '<button class="' . $class . '" type="submit">' . esc($label) . '</button></form>';
    }
}

if (! function_exists('panel_actions')) {
    /**
     * @param array{primary?: array<string,string>, links?: array<string,string>, edit?: string, toggle?: array{url:string,field:string,id:int,active:bool,label:string}, delete?: array{url:string,field:string,id:int,name:string,label:string}} $o
     */
    function panel_actions(array $o): string
    {
        $html = '<div class="flex flex-wrap items-center justify-end gap-2">';
        foreach ($o['primary'] ?? [] as $label => $url) {
            $html .= '<a class="button-primary button-sm text-center" href="' . site_url($url) . '">' . esc($label) . '</a>';
        }
        foreach ($o['links'] ?? [] as $label => $url) {
            $html .= '<a class="button-secondary button-sm text-center" href="' . site_url($url) . '">' . esc($label) . '</a>';
        }
        if (isset($o['edit'])) {
            $html .= '<a class="button-secondary button-sm text-center" href="' . site_url($o['edit']) . '">Edit</a>';
        }
        if (isset($o['toggle'])) {
            $t = $o['toggle'];
            $verb = $t['active'] ? 'Nonaktifkan' : 'Aktifkan';
            $html .= panel_post($t['url'], [$t['field'] => $t['id']], $verb, 'button-secondary button-sm text-center', $verb . ' ' . $t['label'] . ' ini?');
        }
        if (isset($o['delete'])) {
            $d = $o['delete'];
            $html .= panel_post($d['url'], [$d['field'] => $d['id']], 'Hapus', 'button-danger text-center', 'Hapus ' . $d['label'] . ' "' . $d['name'] . '"? Tindakan ini tidak dapat dibatalkan.');
        }

        return $html . '</div>';
    }
}

if (! function_exists('panel_table')) {
    /**
     * Kartu tabel: judul, tombol tambah, kolom No otomatis, cari/halaman (data-datatable).
     *
     * @param list<string>       $head  Judul kolom tanpa "No"; awali dengan ">" untuk rata kanan.
     * @param list<list<string>> $rows  Sel per baris (HTML siap pakai).
     * @param array{search?: bool, empty?: string, width?: string, create?: string, createUrl?: string} $o
     */
    function panel_table(string $title, array $head, array $rows, array $o = []): string
    {
        $cell = 'px-4 py-3';
        $th = '<th class="' . $cell . ' font-bold">No</th>';
        foreach ($head as $label) {
            $right = str_starts_with($label, '>');
            $th .= '<th scope="col" class="' . $cell . ' font-bold' . ($right ? ' text-right' : '') . '">' . esc(ltrim($label, '>')) . '</th>';
        }
        $body = '';
        foreach ($rows as $i => $cells) {
            $body .= '<tr><td class="' . $cell . ' tabular-nums" data-label="No" data-dt-no>' . ($i + 1) . '</td>';
            foreach ($cells as $index => $html) {
                $right = str_starts_with($head[$index] ?? '', '>');
                $label = ltrim($head[$index] ?? '', '>');
                $body .= '<td class="' . $cell . ($right ? ' text-right tabular-nums' : '') . '" data-label="' . esc($label, 'attr') . '"><span class="panel-data-label" aria-hidden="true">' . esc($label) . '</span>' . $html . '</td>';
            }
            $body .= '</tr>';
        }
        if ($rows === []) {
            $body = '<tr class="panel-table-empty"><td class="px-4 py-6 text-ink-muted" colspan="' . (count($head) + 1) . '">' . esc($o['empty'] ?? 'Belum ada data.') . '</td></tr>';
        }
        $create = isset($o['createUrl'])
            ? '<a class="button-primary" href="' . site_url($o['createUrl']) . '">' . esc($o['create'] ?? 'Tambah data') . '</a>'
            : '';

        return '<section class="panel-card mb-8 overflow-hidden"><div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-warm px-5 py-4">'
            . '<h2 class="font-bold uppercase tracking-wide">' . esc($title) . '</h2>' . $create . '</div>'
            . '<div class="panel-table-scroll max-h-[72vh] overflow-auto"><table class="panel-data-table w-full min-w-[' . ($o['width'] ?? '40rem') . '] text-left text-sm"' . (($o['search'] ?? true) ? ' data-datatable' : '') . '>'
            . '<thead class="bg-surface-soft-yellow"><tr>' . $th . '</tr></thead><tbody class="divide-y divide-border-warm">' . $body . '</tbody></table></div></section>';
    }
}

if (! function_exists('panel_qty')) {
    function panel_qty($value): string
    {
        $text = rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');

        return $text === '' ? '0' : $text;
    }
}

if (! function_exists('panel_rupiah')) {
    function panel_rupiah($value): string
    {
        return 'Rp' . number_format((float) $value, 0, ',', '.');
    }
}
