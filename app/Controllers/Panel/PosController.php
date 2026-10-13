<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use App\Services\SaleService;
use App\Services\ReviewService;
use App\Services\SalesPolicyService;
use App\Services\PosOrderService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class PosController extends BaseController
{
    public function index(): string
    {
        $database = db_connect();
        $userId = (int) session('user_id');
        $shift = $database->table('shifts')->where('cashier_id', $userId)->where('status', 'open')->get()->getRowArray();
        $products = $database->table('product_variants')
            ->select('product_variants.id AS variant_id, product_variants.direct_inventory_item_id, product_variants.name AS variant_name, product_variants.sku, product_variants.price, product_variants.stock_mode, products.id AS product_id, products.name AS product_name, products.image_path, categories.name AS category_name, categories.slug AS category_slug')
            ->join('products', 'products.id = product_variants.product_id')->join('categories', 'categories.id = products.category_id')
            ->where('product_variants.is_active', true)->where('products.is_active', true)->where('categories.is_active', true)
            ->orderBy('categories.sort_order')->orderBy('products.name')->orderBy('product_variants.price')->get()->getResultArray();
        $availability = new \App\Services\ProductAvailabilityService($database);
        $products = $availability->annotateVariants($products);
        $toppings = $availability->annotateToppings($database->table('toppings')->where('is_active', true)->orderBy('name')->get()->getResultArray());
        $orderService = new PosOrderService($database);
        $activeOrderId = filter_var($this->request->getGet('order'), FILTER_VALIDATE_INT);
        $activeOrder = $activeOrderId ? $orderService->findHeld((int) $activeOrderId, $userId) : null;
        return view('panel/pos/index', [
            'title' => 'POS', 'user' => session('user'), 'shift' => $shift, 'products' => $products, 'toppings' => $toppings,
            'idempotencyKey' => bin2hex(random_bytes(24)),
            'heldOrders' => $orderService->heldForCashier($userId), 'activeOrder' => $activeOrder,
            'policy' => (new SalesPolicyService($database))->current(),
        ]);
    }

    public function finalize(): RedirectResponse
    {
        $cart = json_decode((string) $this->request->getPost('cart_payload'), true);
        $payments = json_decode((string) $this->request->getPost('payment_payload'), true);
        $transactionDiscount = json_decode((string) $this->request->getPost('transaction_discount_payload'), true);
        if (!is_array($cart) || !is_array($payments) || !is_array($transactionDiscount)) {
            return redirect()->route('panel.pos')->with('error', 'Data keranjang atau pembayaran tidak valid.');
        }
        try {
            $saleId = (new SaleService())->finalize([
                'idempotency_key' => (string) $this->request->getPost('idempotency_key'),
                'cart' => $cart,
                'payments' => $payments,
                'transaction_discount' => $transactionDiscount,
                'pos_order_id' => (string) $this->request->getPost('pos_order_id'),
                'order_type' => (string) $this->request->getPost('order_type'),
                'table_number' => (string) $this->request->getPost('table_number'),
                'customer_name' => (string) $this->request->getPost('customer_name'),
                'discount_approval' => [
                    'identity' => (string) $this->request->getPost('discount_approval_identity'),
                    'password' => (string) $this->request->getPost('discount_approval_password'),
                ],
            ], (int) session('user_id'));
        } catch (\Throwable $exception) {
            $orderId = filter_var($this->request->getPost('pos_order_id'), FILTER_VALIDATE_INT);
            $destination = $orderId ? site_url('panel/pos?order=' . (int) $orderId) : site_url('panel/pos');
            return redirect()->to($destination)->with('error', $exception->getMessage());
        }
        try { (new ReviewService())->issueToken($saleId); }
        catch (\Throwable) { /* Review link creation must not undo a completed sale. */ }
        return redirect()->to(site_url('panel/transactions/' . $saleId))->with('success', 'Pembayaran berhasil disimpan.');
    }

    public function saveOrder(): RedirectResponse
    {
        $cart = json_decode((string) $this->request->getPost('cart_payload'), true);
        if (!is_array($cart)) {
            return redirect()->route('panel.pos')->with('error', 'Data keranjang tidak valid.');
        }
        $rawOrderId = trim((string) $this->request->getPost('pos_order_id'));
        $orderId = $rawOrderId === '' ? null : filter_var($rawOrderId, FILTER_VALIDATE_INT);
        if ($orderId === false || ($orderId !== null && $orderId < 1)) {
            return redirect()->route('panel.pos')->with('error', 'Pesanan tersimpan tidak valid.');
        }
        try {
            $savedId = (new PosOrderService())->save([
                'cart' => $cart,
                'order_type' => (string) $this->request->getPost('order_type'),
                'table_number' => (string) $this->request->getPost('table_number'),
                'customer_name' => (string) $this->request->getPost('customer_name'),
            ], (int) session('user_id'), $orderId === null ? null : (int) $orderId);
        } catch (\Throwable $exception) {
            $destination = $orderId ? site_url('panel/pos?order=' . (int) $orderId) : site_url('panel/pos');
            return redirect()->to($destination)->with('error', $exception->getMessage());
        }

        return redirect()->to(site_url('panel/pos?order=' . $savedId))->with('success', 'Pesanan tersimpan. Stok belum dipotong.');
    }

    public function cancelOrder(int $orderId): RedirectResponse
    {
        try {
            (new PosOrderService())->cancel($orderId, (int) session('user_id'));
        } catch (\Throwable $exception) {
            return redirect()->route('panel.pos')->with('error', $exception->getMessage());
        }

        return redirect()->route('panel.pos')->with('success', 'Pesanan dibatalkan. Tidak ada penjualan atau mutasi stok yang dibuat.');
    }

    public function transactions(): string
    {
        $database = db_connect();
        $builder = $database->table('sales')->select('sales.*, users.name AS cashier_name, shifts.shift_number, (SELECT COUNT(*) FROM refunds WHERE refunds.sale_id = sales.id AND refunds.status = "submitted") AS pending_refund_count', false)->join('users', 'users.id = sales.cashier_id')->join('shifts', 'shifts.id = sales.shift_id')->orderBy('sales.paid_at', 'DESC');
        return view('panel/transactions/index', ['title' => 'Transaksi', 'user' => session('user'), 'sales' => $builder->limit(100)->get()->getResultArray()]);
    }

    public function receipt(int $saleId): string
    {
        return view('panel/transactions/receipt', $this->receiptPayload($saleId));
    }

    public function receiptPdf(int $saleId): ResponseInterface
    {
        $data = $this->receiptPayload($saleId);
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('panel/transactions/receipt_pdf', $data), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="struk-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $data['sale']['sale_number']) . '.pdf"')
            ->setBody($pdf->output());
    }

    private function receiptPayload(int $saleId): array
    {
        $database = db_connect();
        $sale = $database->table('sales')->select('sales.*, users.name AS cashier_name, shifts.shift_number')->join('users', 'users.id = sales.cashier_id')->join('shifts', 'shifts.id = sales.shift_id')->where('sales.id', $saleId)->get()->getRowArray();
        if (!$sale || ((session('user')['role'] ?? '') === 'cashier' && (int) $sale['cashier_id'] !== (int) session('user_id'))) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Transaksi tidak ditemukan.');
        }
        try { $reviewToken = (new ReviewService())->activeToken($saleId); }
        catch (\Throwable) { $reviewToken = null; }
        $items = $database->table('sale_items')->where('sale_id', $saleId)->get()->getResultArray();
        foreach ($items as &$item) {
            $item['posted_refund_quantity'] = (float) ($database->table('refund_items')->selectSum('refund_items.quantity', 'total')->join('refunds', 'refunds.id = refund_items.refund_id')->where('refund_items.sale_item_id', $item['id'])->where('refunds.status', 'posted')->get()->getRow('total') ?? 0);
            $item['reserved_refund_quantity'] = (float) ($database->table('refund_items')->selectSum('refund_items.quantity', 'total')->join('refunds', 'refunds.id = refund_items.refund_id')->where('refund_items.sale_item_id', $item['id'])->whereIn('refunds.status', ['submitted', 'posted'])->get()->getRow('total') ?? 0);
        }
        unset($item);
        $refunds = $database->table('refunds')->select('refunds.*, requester.name AS requester_name, approver.name AS approver_name, rejecter.name AS rejecter_name')
            ->join('users requester', 'requester.id = refunds.requested_by')->join('users approver', 'approver.id = refunds.approved_by', 'left')->join('users rejecter', 'rejecter.id = refunds.rejected_by', 'left')
            ->where('refunds.sale_id', $saleId)->orderBy('refunds.id', 'DESC')->get()->getResultArray();
        foreach ($refunds as &$refund) {
            $refund['items'] = $database->table('refund_items')->select('refund_items.*, sale_items.product_name, sale_items.variant_name')->join('sale_items', 'sale_items.id = refund_items.sale_item_id')->where('refund_items.refund_id', $refund['id'])->get()->getResultArray();
        }
        unset($refund);
        $settings = [];
        foreach ((new SettingModel())->findAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
        return [
            'title' => 'Struk ' . $sale['sale_number'], 'user' => session('user'), 'sale' => $sale, 'items' => $items,
            'payments' => $database->table('payments')->where('sale_id', $saleId)->get()->getResultArray(), 'refunds' => $refunds,
            'postedRefundTotal' => (float) ($database->table('refunds')->selectSum('amount', 'total')->where('sale_id', $saleId)->where('status', 'posted')->get()->getRow('total') ?? 0),
            'reviewToken' => $reviewToken, 'businessSettings' => $settings,
        ];
    }

    public function reviewLink(int $saleId): RedirectResponse
    {
        try { $token = (new ReviewService())->issueToken($saleId, true); }
        catch (\Throwable $exception) { return redirect()->to(site_url('panel/transactions/' . $saleId))->with('error', $exception->getMessage()); }
        if (!$token) { return redirect()->to(site_url('panel/transactions/' . $saleId))->with('error', 'Tautan ulasan belum dapat dibuat.'); }
        return redirect()->to(site_url('panel/transactions/' . $saleId))->with('success', 'Tautan ulasan baru dibuat. Tautan sebelumnya dinonaktifkan.');
    }
}
