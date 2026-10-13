<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Models\BannerModel;
use App\Models\SettingModel;
use App\Services\ImageUploadService;
use App\Services\Money;
use App\Services\SalesPolicyService;
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
            'policy' => (new SalesPolicyService())->current(),
            'banners' => (new BannerModel())->orderBy('sort_order')->orderBy('id', 'DESC')->findAll(),
        ]);
    }

    public function saveSalesPolicy(): RedirectResponse
    {
        $values = [];
        foreach (SalesPolicyService::defaults() as $key => $default) {
            $value = trim((string) $this->request->getPost($key));
            if (in_array($key, ['service_charge_enabled', 'tax_enabled'], true)) {
                if (!in_array($value, ['0', '1'], true)) {
                    return redirect()->back()->withInput()->with('error', 'Status pajak dan biaya layanan tidak valid.');
                }
            } elseif (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $value)) {
                return redirect()->back()->withInput()->with('error', 'Batas diskon dan tarif harus berupa angka positif dengan maksimal dua angka desimal.');
            }
            $values[$key] = $value;
        }

        foreach (['item_discount_max_amount', 'transaction_discount_max_amount'] as $key) {
            if (Money::cents($values[$key]) % 100 !== 0) {
                return redirect()->back()->withInput()->with('error', 'Batas nominal diskon harus berupa Rupiah bulat.');
            }
        }

        foreach (['item_discount_max_percent', 'transaction_discount_max_percent', 'service_charge_rate', 'tax_rate'] as $key) {
            if ((float) $values[$key] > 100) {
                return redirect()->back()->withInput()->with('error', 'Persentase batas diskon, pajak, dan biaya layanan tidak boleh melebihi 100%.');
            }
        }
        if ($values['service_charge_enabled'] === '1' && (float) $values['service_charge_rate'] <= 0) {
            return redirect()->back()->withInput()->with('error', 'Isi tarif biaya layanan sebelum mengaktifkannya.');
        }
        if ($values['tax_enabled'] === '1' && (float) $values['tax_rate'] <= 0) {
            return redirect()->back()->withInput()->with('error', 'Isi tarif pajak sebelum mengaktifkannya.');
        }

        $database = db_connect();
        $database->transStart();
        foreach ($values as $key => $value) {
            $database->table('settings')->replace([
                'setting_key' => $key,
                'setting_value' => $value,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $database->transComplete();
        if ($database->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'Kebijakan harga tidak berhasil disimpan.');
        }
        return redirect()->route('panel.settings')->with('success', 'Kebijakan diskon, pajak, dan biaya layanan berhasil disimpan.');
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
        $builder->replace(['setting_key' => 'business_profile_is_demo', 'setting_value' => '0', 'updated_at' => date('Y-m-d H:i:s')]);
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
        $file = $this->request->getFile('image');
        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return redirect()->back()->withInput()->with('error', 'Pilih gambar untuk banner.');
        }
        try {
            $imagePath = (new ImageUploadService())->store($file, 'banners', 3 * 1024 * 1024);
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
        $database = db_connect();
        try {
            $database->transException(true)->transStart();
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
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            $this->deleteMediaIfUnused($imagePath);
            return redirect()->back()->withInput()->with('error', 'Banner belum dapat disimpan.');
        }
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

    public function deleteBanner(): RedirectResponse
    {
        $id = filter_var($this->request->getPost('banner_id'), FILTER_VALIDATE_INT);
        $banner = $id ? (new BannerModel())->find($id) : null;
        if (!$banner) {
            return redirect()->route('panel.settings')->with('error', 'Banner tidak ditemukan.');
        }
        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            (new BannerModel())->delete($id);
            $database->transComplete();
        } catch (\Throwable) {
            $database->transRollback();
            return redirect()->route('panel.settings')->with('error', 'Banner belum dapat dihapus.');
        }
        $this->deleteMediaIfUnused($banner['image_path']);

        return redirect()->route('panel.settings')->with('success', 'Banner berhasil dihapus.');
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
        $data = [
            'title' => trim((string) $this->request->getPost('title')),
            'alt_text' => trim((string) $this->request->getPost('alt_text')),
            'link_url' => $link ?: null,
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
        $file = $this->request->getFile('image');
        $newPath = null;
        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            try { $newPath = (new ImageUploadService())->store($file, 'banners', 3 * 1024 * 1024); }
            catch (RuntimeException $exception) { return redirect()->back()->withInput()->with('error', $exception->getMessage()); }
            $data['image_path'] = $newPath;
        }
        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            $model->update($banner['id'], $data);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            if ($newPath) { $this->deleteMediaIfUnused($newPath); }
            return redirect()->route('panel.settings')->with('error', 'Banner belum dapat diperbarui.');
        }
        if ($newPath) { $this->deleteMediaIfUnused($banner['image_path']); }
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

    private function deleteMediaIfUnused(string $relativePath): void
    {
        $database = db_connect();
        foreach (['product_images', 'products', 'banners'] as $table) {
            if ($database->table($table)->where('image_path', $relativePath)->countAllResults() > 0) { return; }
        }
        $uploadsRoot = realpath(FCPATH . 'uploads');
        $path = realpath(FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($uploadsRoot && $path && str_starts_with($path, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($path)) { @unlink($path); }
    }
}
