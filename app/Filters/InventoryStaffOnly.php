<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class InventoryStaffOnly implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = session('user');
        if (!is_array($user) || !in_array($user['role'] ?? null, ['owner', 'cashier'], true)) {
            return redirect()->route('panel')->with('error', 'Penerimaan dan kartu stok hanya dapat diakses owner atau kasir.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
