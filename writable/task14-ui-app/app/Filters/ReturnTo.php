<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Setelah simpan berhasil, kembali ke halaman asal form (field `_return`)
 * alih-alih ke halaman modul lama.
 */
class ReturnTo implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $return = (string) $request->getPost('_return');
        if (strtolower($request->getMethod()) === 'post' && preg_match('#^panel/[a-z0-9/_-]*$#i', $return) && $response->hasHeader('Location') && session()->has('success')) {
            $response->setHeader('Location', site_url($return));
        }

        return $response;
    }
}
