<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use App\Services\SalesReportService;
use CodeIgniter\HTTP\ResponseInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class ReportController extends BaseController
{
    public function index(): string
    {
        try {
            $service = new SalesReportService();
            $report = $service->build($this->request->getGet(), $this->isOwner());
        } catch (\Throwable $exception) {
            return view('panel/reports/index', ['title' => 'Laporan penjualan', 'user' => session('user'), 'reportError' => $exception->getMessage(), 'report' => null, 'options' => (new SalesReportService())->options()]);
        }
        return view('panel/reports/index', ['title' => 'Laporan penjualan', 'user' => session('user'), 'report' => $report, 'reportError' => null, 'options' => $service->options()]);
    }

    public function pdf(): ResponseInterface
    {
        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            $service = new SalesReportService($database);
            $report = $service->build($this->request->getGet(), $this->isOwner());
            $optionLists = $service->options();
            $settings = [];
            foreach ((new SettingModel())->findAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->route('panel.reports')->with('error', $exception->getMessage());
        }

        $filterLabels = $this->filterLabels($report['filters'], $optionLists);
        $html = view('panel/reports/pdf', [
            'report' => $report, 'filterLabels' => $filterLabels, 'settings' => $settings,
            'businessName' => $settings['business_name'] ?? env('BUSINESS_NAME', 'Marwis Sweet'),
            'printedBy' => session('user')['name'] ?? '-', 'printedAt' => date('d/m/Y H:i'),
        ]);
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $filename = 'laporan-penjualan-' . $report['period']['type'] . '-' . $report['period']['value'] . '.pdf';
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }

    private function isOwner(): bool
    {
        return (session('user')['role'] ?? '') === 'owner';
    }

    private function filterLabels(array $filters, array $options): array
    {
        $labels = [];
        $maps = ['cashier_id' => ['Kasir', 'cashiers'], 'product_id' => ['Produk', 'products'], 'category_id' => ['Kategori', 'categories'], 'shift_id' => ['Shift', 'shifts']];
        foreach ($maps as $key => [$label, $optionKey]) {
            if (!$filters[$key]) { continue; }
            foreach ($options[$optionKey] as $option) {
                if ((int) $option['id'] === (int) $filters[$key]) { $labels[] = $label . ': ' . ($option['name'] ?? $option['shift_number']); break; }
            }
        }
        if ($filters['method']) { $labels[] = 'Pembayaran: ' . strtoupper(str_replace('_', ' ', $filters['method'])); }
        return $labels;
    }
}
