<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

class ReviewImageService
{
    public function store(UploadedFile $file): string
    {
        if (!$file->isValid() || $file->hasMoved() || $file->getSize() > 2 * 1024 * 1024) { throw new RuntimeException('Setiap foto harus valid dan berukuran maksimal 2 MB.'); }
        $info = @getimagesize($file->getTempName());
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) { throw new RuntimeException('Foto ulasan harus berupa JPEG, PNG, atau WebP yang valid.'); }
        $source = match ($info[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getTempName()), IMAGETYPE_PNG => @imagecreatefrompng($file->getTempName()), IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getTempName()) : false };
        if ($source === false) { throw new RuntimeException('Foto ulasan tidak dapat diproses.'); }
        $directory = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'reviews';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) { imagedestroy($source); throw new RuntimeException('Folder foto ulasan tidak dapat dibuat.'); }
        $filename = bin2hex(random_bytes(16)) . '.jpg';
        $target = $directory . DIRECTORY_SEPARATOR . $filename;
        $width = imagesx($source); $height = imagesy($source); $canvas = imagecreatetruecolor($width, $height); $white = imagecolorallocate($canvas, 255, 255, 255); imagefill($canvas, 0, 0, $white); imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        $saved = imagejpeg($canvas, $target, 88); imagedestroy($source); imagedestroy($canvas);
        if (!$saved) { throw new RuntimeException('Foto ulasan belum dapat disimpan.'); }
        return 'uploads/private/reviews/' . $filename;
    }
}
