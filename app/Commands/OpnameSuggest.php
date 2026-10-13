<?php

namespace App\Commands;

use App\Services\OpnameSuggestionService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class OpnameSuggest extends BaseCommand
{
    protected $group = 'Marwis';
    protected $name = 'opname:suggest';
    protected $description = 'Menyusun usulan stok opname dari stok minimum, aktivitas transaksi, dan jadwal hitung.';

    public function run(array $params): void
    {
        $created = (new OpnameSuggestionService())->generate();
        CLI::write($created . ' usulan opname baru dibuat.', $created > 0 ? 'green' : 'yellow');
    }
}
