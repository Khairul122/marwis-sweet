<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

class EvidenceUploadService
{
    public function store(UploadedFile $file, string $bucket = 'expenses'): array
    {
        if (!in_array($bucket, ['expenses', 'opnames'], true)) {
            throw new RuntimeException('Lokasi penyimpanan bukti tidak valid.');
        }
        if (!$file->isValid() || $file->hasMoved()) {
            throw new RuntimeException('Berkas bukti tidak dapat dibaca.');
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new RuntimeException('Ukuran bukti maksimal 5 MB.');
        }
        $directory = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . $bucket;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Folder bukti privat tidak dapat dibuat.');
        }
        $originalName = mb_substr(basename($file->getClientName()), 0, 255);
        $imageInfo = @getimagesize($file->getTempName());
        if ($imageInfo !== false && in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return $this->storeImage($file, $directory, $originalName, $imageInfo[2], $bucket);
        }
        $header = file_get_contents($file->getTempName(), false, null, 0, 5);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getTempName());
        if ($mime !== 'application/pdf' || $header !== '%PDF-') {
            throw new RuntimeException('Bukti harus berupa JPEG, PNG, WebP, atau PDF yang valid.');
        }
        $filename = bin2hex(random_bytes(16)) . '.pdf';
        if (!copy($file->getTempName(), $directory . DIRECTORY_SEPARATOR . $filename)) {
            throw new RuntimeException('Bukti belum dapat disimpan.');
        }
        return ['path' => 'uploads/private/' . $bucket . '/' . $filename, 'name' => $originalName, 'mime' => 'application/pdf'];
    }

    private function storeImage(UploadedFile $file, string $directory, string $originalName, int $type, string $bucket): array
    {
        if ($type === IMAGETYPE_WEBP && !function_exists('imagecreatefromwebp')) {
            throw new RuntimeException('Dukungan WebP belum aktif. Gunakan JPEG atau PNG.');
        }
        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getTempName()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getTempName()),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file->getTempName()),
        };
        if ($source === false) {
            throw new RuntimeException('Isi gambar bukti tidak dapat diproses.');
        }
        $extension = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$type];
        $mime = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'][$type];
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $target = $directory . DIRECTORY_SEPARATOR . $filename;
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($source, false);
            imagesavealpha($source, true);
        }
        $saved = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($source, $target, 88),
            IMAGETYPE_PNG => imagepng($source, $target, 7),
            IMAGETYPE_WEBP => function_exists('imagewebp') && imagewebp($source, $target, 88),
        };
        imagedestroy($source);
        if (!$saved) {
            throw new RuntimeException('Gambar bukti belum dapat disimpan.');
        }
        return ['path' => 'uploads/private/' . $bucket . '/' . $filename, 'name' => $originalName, 'mime' => $mime];
    }
}
