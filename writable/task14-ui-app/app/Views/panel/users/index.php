<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$rows = array_map(static fn (array $c): array => [
    '<strong>' . esc($c['name']) . '</strong>', esc($c['username']), esc($c['email'] ?: '-'), panel_badge($c['status'] === 'active'),
    panel_actions([
        'links' => ['Reset password' => 'panel/data/password/' . $c['id'] . '/edit'],
        'edit' => 'panel/data/cashier/' . $c['id'] . '/edit',
        'toggle' => ['url' => 'panel/users/status', 'field' => 'user_id', 'id' => $c['id'], 'active' => $c['status'] === 'active', 'label' => 'akun kasir'],
    ]),
], $cashiers);
?>
<div class="mb-6">
    <p class="section-kicker">Pengaturan akses</p>
    <h1 class="mt-2 font-display text-3xl leading-tight sm:text-4xl">Akun kasir</h1>
</div>

<?= panel_table('Semua data akun kasir', ['Nama', 'Username', 'Email', 'Status', '>Aksi'], $rows, ['createUrl' => 'panel/data/cashier/create', 'width' => '44rem', 'empty' => 'Belum ada akun kasir.']) ?>
<?= $this->endSection() ?>
