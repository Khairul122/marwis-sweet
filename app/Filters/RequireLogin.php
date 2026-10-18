<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RequireLogin implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = session('user_id');
        if (!$userId) {
            $path = uri_string();
            if (strtolower($request->getMethod()) === 'get' && !$request->isAJAX()
                && !str_ends_with($path, '.pdf')
                && in_array($request->getHeaderLine('Sec-Fetch-Dest'), ['', 'document'], true)) {
                $query = $request->getUri()->getQuery();
                session()->set('login_intended', $path . ($query !== '' ? '?' . $query : ''));
            }
            return redirect()->route('login')->with('error', 'Silakan masuk untuk melanjutkan.');
        }

        $user = (new UserModel())->select('id, name, username, role, status')->find($userId);
        if (!$user || $user['status'] !== 'active') {
            session()->destroy();
            return redirect()->route('login')->with('error', 'Sesi berakhir atau akun tidak aktif.');
        }

        session()->set('user', $user);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
