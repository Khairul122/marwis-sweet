<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $dateFormatter = new \IntlDateFormatter(
            'id_ID',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
            'Asia/Jakarta',
            \IntlDateFormatter::GREGORIAN,
            'EEEE, d MMMM y',
        );

        return view('panel/dashboard', [
            'title' => 'Dashboard',
            'user' => session('user'),
            'todayLabel' => $dateFormatter->format(new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta'))),
        ]);
    }
}
