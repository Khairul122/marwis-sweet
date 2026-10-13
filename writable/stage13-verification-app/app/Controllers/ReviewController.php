<?php

namespace App\Controllers;

use App\Models\SettingModel;
use App\Services\ReviewService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class ReviewController extends BaseController
{
    public function form(string $token): string
    {
        try { $context = (new ReviewService())->resolve($token); }
        catch (\Throwable $exception) { return view('public/review_unavailable', ['message' => $exception->getMessage(), 'businessName' => $this->businessName()]); }
        $attempts = (new ReviewService())->attemptCount($token, $this->request->getIPAddress());
        $captcha = null;
        if ($attempts >= 3) {
            $a = random_int(2, 9); $b = random_int(1, 9); $captcha = $a . ' + ' . $b;
            session()->set('review_captcha_' . substr(hash('sha256', $token), 0, 16), $a + $b);
        }
        return view('public/review_form', ['businessName' => $this->businessName(), 'token' => $token, 'context' => $context, 'captcha' => $captcha]);
    }

    public function submit(string $token): RedirectResponse
    {
        if (trim((string) $this->request->getPost('website')) !== '') { return redirect()->to(site_url('ulasan/' . $token))->with('review_success', 'Ulasan diterima dan menunggu moderasi.'); }
        $service = new ReviewService();
        $attempts = $service->attemptCount($token, $this->request->getIPAddress());
        if ($attempts >= 3) {
            $key = 'review_captcha_' . substr(hash('sha256', $token), 0, 16);
            $answer = session()->get($key); session()->remove($key);
            if ($answer === null || (int) $this->request->getPost('captcha_answer') !== (int) $answer) { return redirect()->to(site_url('ulasan/' . $token))->withInput()->with('review_error', 'Jawaban verifikasi belum tepat.'); }
        }
        try {
            $service->submit($token, $this->request->getPost(), $this->request->getFileMultiple('photos') ?? [], $this->request->getIPAddress());
        } catch (\Throwable $exception) { return redirect()->to(site_url('ulasan/' . $token))->withInput()->with('review_error', $exception->getMessage()); }
        return redirect()->to(site_url('ulasan/' . $token))->with('review_success', 'Terima kasih. Ulasan tersimpan dan akan tampil setelah diperiksa.');
    }

    public function photo(int $imageId): ResponseInterface
    {
        $image = db_connect()->table('review_images')->select('review_images.image_path, product_reviews.status')->join('product_reviews', 'product_reviews.id = review_images.product_review_id')->where('review_images.id', $imageId)->get()->getRowArray();
        $owner = (session('user')['role'] ?? '') === 'owner';
        if (!$image || ($image['status'] !== 'approved' && !$owner)) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Foto tidak ditemukan.'); }
        $base = realpath(WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'reviews');
        $path = realpath(WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $image['image_path']));
        if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Foto tidak ditemukan.'); }
        return $this->response->setHeader('Content-Type', 'image/jpeg')->setHeader('Cache-Control', 'private, max-age=3600')->setBody(file_get_contents($path));
    }

    private function businessName(): string
    {
        $setting = (new SettingModel())->where('setting_key', 'business_name')->first();
        return (string) ($setting['setting_value'] ?? env('BUSINESS_NAME', 'Marwis Sweet'));
    }
}
