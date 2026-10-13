<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Models\BannerModel;
use App\Models\SettingModel;
use App\Services\ImageUploadService;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

class SettingsController extends BaseController
{
    public function index(): string
    {
        $settings = [];
        foreach ((new SettingModel())->findAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return view('panel/settings/index', [
            'title' => 'Identitas usaha dan banner',
            'user' => session('user'),
            'settings' => $settings,
            'banners' => (new BannerModel())->orderBy('sort_order')->orderBy('id', 'DESC')->findAll(),
        ]);
    }

    public function saveBusiness(): RedirectResponse
    {
        $rules = [
            'business_name' => 'required|min_length[2]|max_length[120]',
            'address' => 'permit_empty|max_length[500]',
            'phone' => 'permit_empty|max_length[40]',
            'opening_hours' => 'permit_empty|max_length[255]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        $builder = db_connect()->table('settings');
        foreach (['business_name', 'address', 'phone', 'opening_hours'] as $key) {
            $value = trim((string) $this->request->getPost($key));
            $builder->replace(['setting_key' => $key, 'setting_value' => $value ?: null, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        return redirect()->route('panel.settings')->with('success', 'Informasi usaha berhasil disimpan.');
    }

    public function createBanner(): RedirectResponse
    {
        $rules = [
            'title' => 'required|min_length[2]|max_length[140]',
            'alt_text' => 'required|min_length[5]|max_length[180]',
            'link_url' => 'permit_empty|max_length[500]',
            'sort_order' => 'permit_empty|is_natural|max_length[5]',
            'starts_at' => 'permit_empty|valid_date[Y-m-d\TH:i]',
            'ends_at' => 'permit_empty|valid_date[Y-m-d\TH:i]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        $link = trim((string) $this->request->getPost('link_url'));
        if ($link !== '' && !$this->isSafeLink($link)) {
            return redirect()->back()->withInput()->with('error', 'Tautan banner harus berupa URL internal, HTTP, atau HTTPS.');
        }
        $startsAt = $this->toDatabaseDate((string) $this->request->getPost('starts_at'));
        $endsAt = $this->toDatabaseDate((string) $this->request->getPost('ends_at'));
        if ($startsAt && $endsAt && $endsAt <= $startsAt) {
            return redirect()->back()->withInput()->with('error', 'Waktu selesai harus setelah waktu mulai.');
        }
        try {
            $imagePath = (new ImageUploadService())->store($this->request->getFile('image'), 'banners', 3 * 1024 * 1024);
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
        (new BannerModel())->insert([
            'title' => trim((string) $this->request->getPost('title')),
            'image_path' => $imagePath,
            'alt_text' => trim((string) $this->request->getPost('alt_text')),
            'link_url' => $link ?: null,
            'sort_order' => (int) ($this->request->getPost('sort_order') ?: 0),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => true,
        ]);
        return redirect()->route('panel.settings')->with('success', 'Banner berhasil ditambahkan.');
    }

    public function toggleBanner(): RedirectResponse
    {
        $id = filter_var($this->request->getPost('banner_id'), FILTER_VALIDATE_INT);
        $model = new BannerModel();
        $banner = $id ? $model->find($id) : null;
        if (!$banner) {
            return redirect()->route('panel.settings')->with('error', 'Banner tidak ditemukan.');
        }
        $model->update($banner['id'], ['is_active' => !$banner['is_active']]);
        return redirect()->route('panel.settings')->with('success', $banner['is_active'] ? 'Banner dinonaktifkan.' : 'Banner diaktifkan.');
    }

    public function updateBanner(): RedirectResponse
    {
        $id = filter_var($this->request->getPost('banner_id'), FILTER_VALIDATE_INT);
        $model = new BannerModel();
        $banner = $id ? $model->find($id) : null;
        if (!$banner) {
            return redirect()->route('panel.settings')->with('error', 'Banner tidak ditemukan.');
        }
        $rules = [
            'title' => 'required|min_length[2]|max_length[140]',
            'alt_text' => 'required|min_length[5]|max_length[180]',
            'link_url' => 'permit_empty|max_length[500]',
            'sort_order' => 'required|is_natural|max_length[5]',
            'starts_at' => 'permit_empty|valid_date[Y-m-d\TH:i]',
            'ends_at' => 'permit_empty|valid_date[Y-m-d\TH:i]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        $link = trim((string) $this->request->getPost('link_url'));
        if ($link !== '' && !$this->isSafeLink($link)) {
            return redirect()->route('panel.settings')->with('error', 'Tautan banner harus berupa URL internal, HTTP, atau HTTPS.');
        }
        $startsAt = $this->toDatabaseDate((string) $this->request->getPost('starts_at'));
        $endsAt = $this->toDatabaseDate((string) $this->request->getPost('ends_at'));
        if ($startsAt && $endsAt && $endsAt <= $startsAt) {
            return redirect()->route('panel.settings')->with('error', 'Waktu selesai harus setelah waktu mulai.');
        }
        $model->update($banner['id'], [
            'title' => trim((string) $this->request->getPost('title')),
            'alt_text' => trim((string) $this->request->getPost('alt_text')),
            'link_url' => $link ?: null,
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        return redirect()->route('panel.settings')->with('success', 'Banner berhasil diperbarui.');
    }

    private function isSafeLink(string $link): bool
    {
        if (str_starts_with($link, '/') && !str_starts_with($link, '//')) {
            return true;
        }
        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) && filter_var($link, FILTER_VALIDATE_URL) !== false;
    }

    private function toDatabaseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, new \DateTimeZone('Asia/Jakarta'));
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }
}
