<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Services\StockOpnameService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class StockOpnameController extends BaseController
{
    public function index(): string
    {
        $database = db_connect();
        $opnames = $database->table('stock_opnames')
            ->select('stock_opnames.*, creator.name AS creator_name, approver.name AS approver_name')
            ->join('users creator', 'creator.id = stock_opnames.created_by')
            ->join('users approver', 'approver.id = stock_opnames.approved_by', 'left')
            ->orderBy('stock_opnames.id', 'DESC')->limit(40)->get()->getResultArray();
        $opnameItems = $database->table('stock_opname_items')
            ->select('stock_opname_items.*, inventory_items.name AS item_name, inventory_items.sku, inventory_items.base_unit')
            ->join('inventory_items', 'inventory_items.id = stock_opname_items.inventory_item_id')
            ->orderBy('inventory_items.name')->get()->getResultArray();
        $itemsByOpname = [];
        foreach ($opnameItems as $item) {
            $itemsByOpname[$item['stock_opname_id']][] = $item;
        }
        return view('panel/opnames/index', [
            'title' => 'Stok opname',
            'user' => session('user'),
            'inventoryItems' => $database->table('inventory_items')->where('is_active', true)->orderBy('name')->get()->getResultArray(),
            'opnames' => $opnames,
            'itemsByOpname' => $itemsByOpname,
        ]);
    }

    public function create(): RedirectResponse
    {
        try {
            (new StockOpnameService())->create([
                'scope' => $this->request->getPost('scope'),
                'item_ids' => $this->request->getPost('item_ids') ?? [],
                'notes' => $this->request->getPost('notes'),
            ], (int) session('user_id'));
        } catch (\Throwable $exception) {
            return redirect()->route('panel.opnames')->with('error', $exception->getMessage());
        }
        return redirect()->route('panel.opnames')->with('success', 'Sesi opname dibuat. Item terpilih dikunci sampai sesi selesai.');
    }

    public function saveCounts(int $opnameId): RedirectResponse
    {
        try {
            (new StockOpnameService())->saveCounts(
                $opnameId,
                (array) ($this->request->getPost('physical_quantities') ?? []),
                (array) ($this->request->getPost('item_notes') ?? []),
                (int) session('user_id'),
                (string) (session('user')['role'] ?? ''),
            );
        } catch (\Throwable $exception) {
            return redirect()->route('panel.opnames')->with('error', $exception->getMessage());
        }
        return redirect()->route('panel.opnames')->with('success', 'Hasil hitung fisik disimpan.');
    }

    public function submit(int $opnameId): RedirectResponse
    {
        try {
            (new StockOpnameService())->submit($opnameId, (int) session('user_id'), (string) (session('user')['role'] ?? ''));
        } catch (\Throwable $exception) {
            return redirect()->route('panel.opnames')->with('error', $exception->getMessage());
        }
        return redirect()->route('panel.opnames')->with('success', 'Opname dikirim untuk ditinjau owner.');
    }

    public function uploadEvidence(int $opnameId): RedirectResponse
    {
        try {
            (new StockOpnameService())->attachEvidence($opnameId, $this->request->getFile('evidence'), (int) session('user_id'), (string) (session('user')['role'] ?? ''));
        } catch (\Throwable $exception) {
            return redirect()->route('panel.opnames')->with('error', $exception->getMessage());
        }
        return redirect()->route('panel.opnames')->with('success', 'Bukti stok opname disimpan secara privat.');
    }

    public function evidence(int $opnameId): ResponseInterface
    {
        $opname = db_connect()->table('stock_opnames')->where('id', $opnameId)->get()->getRowArray();
        if (!$opname || !$opname['evidence_path'] || ((session('user')['role'] ?? '') !== 'owner' && (int) $opname['created_by'] !== (int) session('user_id'))) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bukti tidak ditemukan.');
        }
        $base = realpath(WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'opnames');
        $path = realpath(WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $opname['evidence_path']));
        if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bukti tidak ditemukan.');
        }
        return $this->response->download($path, null)->setFileName($opname['evidence_name'] ?: basename($path));
    }

    public function cancel(int $opnameId): RedirectResponse
    {
        try {
            (new StockOpnameService())->cancel($opnameId, (string) $this->request->getPost('cancellation_reason'), (int) session('user_id'), (string) (session('user')['role'] ?? ''));
        } catch (\Throwable $exception) {
            return redirect()->route('panel.opnames')->with('error', $exception->getMessage());
        }
        return redirect()->route('panel.opnames')->with('success', 'Sesi opname dibatalkan dan kunci stok dibuka.');
    }

    public function approve(int $opnameId): RedirectResponse
    {
        try {
            (new StockOpnameService())->approve($opnameId, (array) ($this->request->getPost('adjustment_unit_costs') ?? []), (int) session('user_id'));
        } catch (\Throwable $exception) {
            return redirect()->route('panel.opnames')->with('error', $exception->getMessage());
        }
        return redirect()->route('panel.opnames')->with('success', 'Opname disetujui dan penyesuaian stok sudah diposting.');
    }

    public function reject(int $opnameId): RedirectResponse
    {
        try {
            (new StockOpnameService())->reject($opnameId, (string) $this->request->getPost('review_notes'));
        } catch (\Throwable $exception) {
            return redirect()->route('panel.opnames')->with('error', $exception->getMessage());
        }
        return redirect()->route('panel.opnames')->with('success', 'Opname dikembalikan untuk dikoreksi.');
    }
}
