<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

class ImageUploadService
{
    public function store(UploadedFile $file, string $folder, int $maxBytes): string
    {
        if (!$file->isValid() || $file->hasMoved()) {
            throw new RuntimeException('Berkas gambar tidak dapat dibaca.');
        }
        if ($file->getSize() > $maxBytes) {
            throw new RuntimeException('Ukuran gambar melebihi batas yang diizinkan.');
        }

        $info = @getimagesize($file->getTempName());
        $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if ($info === false || !isset($allowed[$info[2]])) {
            throw new RuntimeException('Gunakan gambar JPEG, PNG, atau WebP yang valid.');
        }

        if ($info[2] === IMAGETYPE_WEBP && !function_exists('imagecreatefromwebp')) {
            throw new RuntimeException('Dukungan WebP belum aktif pada server ini. Gunakan JPEG atau PNG.');
        }
        $source = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getTempName()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getTempName()),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file->getTempName()),
        };
        if ($source === false) {
            throw new RuntimeException('Isi gambar tidak dapat diproses.');
        }

        $relativeDirectory = 'uploads/' . trim($folder, '/');
        $targetDirectory = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
            imagedestroy($source);
            throw new RuntimeException('Folder penyimpanan gambar tidak dapat dibuat.');
        }

        $extension = $allowed[$info[2]];
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $target = $targetDirectory . DIRECTORY_SEPARATOR . $filename;
        if ($info[2] === IMAGETYPE_PNG) {
            imagealphablending($source, false);
            imagesavealpha($source, true);
        }
        $saved = match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($source, $target, 88),
            IMAGETYPE_PNG => imagepng($source, $target, 7),
            IMAGETYPE_WEBP => function_exists('imagewebp') && imagewebp($source, $target, 88),
        };
        imagedestroy($source);
        if (!$saved) {
            throw new RuntimeException('Gambar belum dapat disimpan.');
        }

        return $relativeDirectory . '/' . $filename;
    }
}
