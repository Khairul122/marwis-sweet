<?php
$toppingId = (int) $hidden['topping_id'];
$returnUrl = 'panel/data/topping/' . $toppingId . '/edit';
$lineRows = array_map(static fn (array $l): array => [
    '<strong>' . esc($l['item_name']) . '</strong>', panel_qty($l['quantity']) . ' ' . esc($l['base_unit']),
    panel_post('panel/master/toppings/items/remove', ['topping_item_id' => $l['id'], '_return' => $returnUrl], 'Hapus', 'button-danger', 'Hapus bahan ini dari topping?'),
], $extra['lines']);
?>
<div class="mt-8 max-w-3xl">
    <?= panel_table('Bahan yang dipakai topping', ['Bahan', '>Jumlah', '>Aksi'], $lineRows, ['search' => false, 'empty' => 'Belum ada bahan.', 'width' => '28rem']) ?>
    <?= view('panel/crud/_line_form', ['formItems' => $extra['items'], 'formUrl' => 'panel/master/toppings/items', 'formField' => 'topping_id', 'formId' => $toppingId, 'returnUrl' => $returnUrl]) ?>
</div>
