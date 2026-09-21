<?php

declare(strict_types=1);

function optimize_avatar_image(string $source, string $destination): void
{
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('Photo uploads are temporarily unavailable.');
    }
    $info = @getimagesize($source);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('Choose a JPG, PNG, or WebP photo.');
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > 8000 || $info[1] > 8000 || $info[0] * $info[1] > 16000000) {
        throw new RuntimeException('Choose a photo with no more than 16 megapixels.');
    }
    $image = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
        IMAGETYPE_PNG => @imagecreatefrompng($source),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
    };
    if (!$image) {
        throw new RuntimeException('This photo could not be read. Try another image.');
    }
    $output = null;
    try {
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($source);
            $orientation = (int) ($exif['Orientation'] ?? 1);
            if (in_array($orientation, [2, 4, 5, 7], true)) {
                imageflip($image, IMG_FLIP_HORIZONTAL);
            }
            $angle = match ($orientation) { 3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0 };
            if ($angle !== 0) {
                $rotated = imagerotate($image, $angle, 0);
                if (!$rotated) { throw new RuntimeException('This photo could not be rotated.'); }
                imagedestroy($image);
                $image = $rotated;
            }
        }
        $side = min(imagesx($image), imagesy($image));
        $size = min(512, $side);
        $output = imagecreatetruecolor($size, $size);
        imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
        imagecopyresampled($output, $image, 0, 0, (int) ((imagesx($image) - $side) / 2), (int) ((imagesy($image) - $side) / 2), $size, $size, $side, $side);
        if (!imagejpeg($output, $destination, 82)) {
            throw new RuntimeException('Your photo could not be stored. Try again later.');
        }
    } finally {
        imagedestroy($image);
        if ($output) { imagedestroy($output); }
    }
}

function store_avatar_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Choose a photo up to 8 MB. The upload may exceed the server limit.');
    }
    $source = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($source) || filesize($source) > 8 * 1024 * 1024) {
        throw new RuntimeException('Choose a photo up to 8 MB.');
    }
    $relative = 'assets/avatars/' . bin2hex(random_bytes(20)) . '.jpg';
    optimize_avatar_image($source, dirname(__DIR__) . '/' . $relative);
    return '/' . $relative;
}
