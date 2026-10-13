<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CashierOnly implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = session('user');
        if (!is_array($user) || ($user['role'] ?? null) !== 'cashier') {
            return redirect()->route('panel')->with('error', 'Fitur ini hanya dapat diakses oleh kasir.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
