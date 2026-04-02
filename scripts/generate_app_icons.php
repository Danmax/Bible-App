<?php

declare(strict_types=1);

$iconDirectory = dirname(__DIR__) . '/assets/icons';
$sourcePath = dirname(__DIR__) . '/assets/images/good-news-app.png';

if (!is_dir($iconDirectory) && !mkdir($iconDirectory, 0775, true) && !is_dir($iconDirectory)) {
    fwrite(STDERR, "Unable to create icon directory.\n");
    exit(1);
}

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Missing icon source image at assets/images/good-news-app.png.\n");
    exit(1);
}

$sourceBytes = file_get_contents($sourcePath);

if ($sourceBytes === false) {
    fwrite(STDERR, "Unable to read icon source image.\n");
    exit(1);
}

$sourceImage = imagecreatefromstring($sourceBytes);

if (!$sourceImage instanceof GdImage) {
    fwrite(STDERR, "Unable to load icon source image.\n");
    exit(1);
}

$baseSize = 1024;
$baseImage = imagecreatetruecolor($baseSize, $baseSize);

if ($baseImage === false) {
    fwrite(STDERR, "Unable to create base image.\n");
    exit(1);
}

imagealphablending($baseImage, true);
imagesavealpha($baseImage, true);
imageantialias($baseImage, true);

$transparent = imagecolorallocatealpha($baseImage, 0, 0, 0, 127);
imagefill($baseImage, 0, 0, $transparent);
$sourceWidth = imagesx($sourceImage);
$sourceHeight = imagesy($sourceImage);
$sourceCrop = min($sourceWidth, $sourceHeight);
$sourceX = (int) floor(($sourceWidth - $sourceCrop) / 2);
$sourceY = (int) floor(($sourceHeight - $sourceCrop) / 2);

imagecopyresampled(
    $baseImage,
    $sourceImage,
    0,
    0,
    $sourceX,
    $sourceY,
    $baseSize,
    $baseSize,
    $sourceCrop,
    $sourceCrop
);

$exports = [
    'app-icon-512.png' => 512,
    'app-icon-192.png' => 192,
    'apple-touch-icon.png' => 180,
    'favicon-32x32.png' => 32,
    'favicon-16x16.png' => 16,
];

$favicon32Bytes = null;

foreach ($exports as $filename => $size) {
    $resized = imagecreatetruecolor($size, $size);
    imagealphablending($resized, true);
    imagesavealpha($resized, true);
    imageantialias($resized, true);
    $transparentFill = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefill($resized, 0, 0, $transparentFill);
    imagecopyresampled($resized, $baseImage, 0, 0, 0, 0, $size, $size, $baseSize, $baseSize);

    $outputPath = $iconDirectory . '/' . $filename;
    imagepng($resized, $outputPath);

    if ($size === 32) {
        ob_start();
        imagepng($resized);
        $favicon32Bytes = ob_get_clean();
    }

}

if ($favicon32Bytes === null) {
    fwrite(STDERR, "Unable to build favicon bytes.\n");
    exit(1);
}

$icoHeader = pack('vvv', 0, 1, 1);
$icoEntry = pack(
    'CCCCvvVV',
    32,
    32,
    0,
    0,
    1,
    32,
    strlen($favicon32Bytes),
    6 + 16
);

file_put_contents($iconDirectory . '/favicon.ico', $icoHeader . $icoEntry . $favicon32Bytes);

$manifest = [
    'name' => 'Good News Bible',
    'short_name' => 'GoodNews',
    'description' => 'Study The Word Bible with reading, prayer, notes, planner tools, and community.',
    'start_url' => '/index.php',
    'display' => 'standalone',
    'background_color' => '#f6efe1',
    'theme_color' => '#22333b',
    'icons' => [
        [
            'src' => '/assets/icons/app-icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
        ],
        [
            'src' => '/assets/icons/app-icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
        ],
    ],
];

file_put_contents(
    $iconDirectory . '/site.webmanifest',
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

fwrite(STDOUT, "Generated icons in assets/icons.\n");

function draw_glow(GdImage $image, int $centerX, int $centerY, int $radius, array $rgb, int $steps): void
{
    for ($index = $steps; $index >= 1; $index--) {
        $alpha = (int) round(118 - (($index / $steps) * 102));
        $color = imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], max(0, min(127, $alpha)));
        $diameter = (int) round($radius * (($index / $steps) + 0.3));
        imagefilledellipse($image, $centerX, $centerY, $diameter * 2, $diameter * 2, $color);
    }
}

function draw_rays(GdImage $image, int $centerX, int $centerY): void
{
    imagesetthickness($image, 8);

    for ($angle = -75; $angle <= 75; $angle += 8) {
        $length = 420 + (($angle + 75) % 3) * 50;
        $endX = (int) round($centerX + cos(deg2rad($angle)) * $length);
        $endY = (int) round($centerY + sin(deg2rad($angle)) * $length);
        $color = imagecolorallocatealpha($image, 255, 250, 220, 88);
        imageline($image, $centerX, $centerY, $endX, $endY, $color);
    }

    imagesetthickness($image, 3);
}

function draw_sparkles(GdImage $image): void
{
    $sparkles = [
        [170, 180, 38],
        [805, 190, 34],
        [255, 310, 32],
        [740, 350, 26],
    ];

    foreach ($sparkles as [$x, $y, $size]) {
        $white = imagecolorallocatealpha($image, 255, 248, 225, 22);
        imagesetthickness($image, 4);
        imageline($image, $x - $size, $y, $x + $size, $y, $white);
        imageline($image, $x, $y - $size, $x, $y + $size, $white);
        imagesetthickness($image, 2);
        imageline($image, $x - (int) ($size * 0.7), $y - (int) ($size * 0.7), $x + (int) ($size * 0.7), $y + (int) ($size * 0.7), $white);
        imageline($image, $x - (int) ($size * 0.7), $y + (int) ($size * 0.7), $x + (int) ($size * 0.7), $y - (int) ($size * 0.7), $white);
    }

    imagesetthickness($image, 1);
}

function draw_clouds(GdImage $image): void
{
    $cloudColor = imagecolorallocatealpha($image, 255, 250, 236, 64);

    foreach ([[110, 450, 220, 130], [890, 480, 210, 120], [135, 720, 240, 130]] as [$x, $y, $w, $h]) {
        imagefilledellipse($image, $x, $y, $w, $h, $cloudColor);
        imagefilledellipse($image, $x + 70, $y - 25, (int) ($w * 0.7), (int) ($h * 0.9), $cloudColor);
        imagefilledellipse($image, $x - 70, $y - 20, (int) ($w * 0.6), (int) ($h * 0.8), $cloudColor);
    }
}

function draw_leaves(GdImage $image): void
{
    $darkLeaf = imagecolorallocatealpha($image, 57, 118, 30, 8);
    $midLeaf = imagecolorallocatealpha($image, 92, 168, 44, 6);
    $lightLeaf = imagecolorallocatealpha($image, 163, 210, 73, 14);

    $leaves = [
        [150, 890, 210, 320, -28],
        [280, 900, 210, 320, 4],
        [400, 920, 220, 330, 22],
        [640, 930, 220, 330, -10],
        [790, 900, 210, 320, 18],
        [910, 870, 200, 300, 36],
    ];

    foreach ($leaves as [$x, $y, $w, $h, $rotation]) {
        draw_leaf($image, $x, $y, $w, $h, $rotation, $darkLeaf, $midLeaf, $lightLeaf);
    }
}

function draw_leaf(
    GdImage $image,
    int $centerX,
    int $centerY,
    int $width,
    int $height,
    int $rotation,
    int $darkColor,
    int $midColor,
    int $lightColor
): void {
    $points = [];

    for ($index = 0; $index < 24; $index++) {
        $angle = deg2rad(($index / 23) * 180);
        $x = sin($angle) * ($width / 2);
        $y = -cos($angle) * ($height / 2);
        $points[] = rotate_point($centerX, $centerY, $x, $y, $rotation);
    }

    for ($index = 23; $index >= 0; $index--) {
        $angle = deg2rad(($index / 23) * 180);
        $x = -sin($angle) * ($width / 2);
        $y = -cos($angle) * ($height / 2);
        $points[] = rotate_point($centerX, $centerY, $x, $y, $rotation);
    }

    imagefilledpolygon($image, flatten_points($points), $darkColor);
    imagefilledellipse($image, $centerX, $centerY + 18, (int) ($width * 0.72), (int) ($height * 0.78), $midColor);
    imagesetthickness($image, 3);
    $tip = rotate_point($centerX, $centerY, 0, -($height / 2), $rotation);
    $stem = rotate_point($centerX, $centerY, 0, $height / 2.2, $rotation);
    imageline($image, $stem[0], $stem[1], $tip[0], $tip[1], $lightColor);
    imagesetthickness($image, 1);
}

function draw_book(GdImage $image): void
{
    $pagesColor = imagecolorallocatealpha($image, 248, 233, 188, 0);
    $pageShadow = imagecolorallocatealpha($image, 217, 191, 126, 10);
    $coverColor = imagecolorallocatealpha($image, 108, 53, 20, 0);
    $coverHighlight = imagecolorallocatealpha($image, 156, 89, 36, 10);
    $edgeColor = imagecolorallocatealpha($image, 91, 41, 15, 0);
    $bookmarkColor = imagecolorallocatealpha($image, 204, 38, 32, 0);
    $bookmarkShade = imagecolorallocatealpha($image, 134, 16, 17, 18);

    $pages = [
        [320, 760],
        [820, 735],
        [905, 885],
        [390, 925],
    ];
    imagefilledpolygon($image, flatten_points($pages), $pagesColor);
    imagepolygon($image, flatten_points($pages), $pageShadow);

    $cover = [
        [210, 475],
        [720, 430],
        [930, 720],
        [420, 790],
    ];
    imagefilledpolygon($image, flatten_points($cover), $coverColor);

    $innerCover = [
        [280, 535],
        [670, 505],
        [826, 704],
        [435, 748],
    ];
    imagefilledpolygon($image, flatten_points($innerCover), $coverHighlight);

    imagesetthickness($image, 6);
    imagepolygon($image, flatten_points($cover), $edgeColor);
    imagepolygon($image, flatten_points($innerCover), $pageShadow);

    imageline($image, 276, 503, 207, 475, $edgeColor);
    imageline($image, 420, 790, 390, 925, $edgeColor);
    imageline($image, 930, 720, 905, 885, $edgeColor);

    $spineArcColor = imagecolorallocatealpha($image, 86, 39, 15, 0);
    imagearc($image, 220, 690, 150, 430, 70, 292, $spineArcColor);
    imagearc($image, 256, 684, 108, 380, 72, 291, $spineArcColor);

    $bookmark = [
        [542, 788],
        [600, 780],
        [598, 935],
        [568, 968],
        [536, 938],
    ];
    imagefilledpolygon($image, flatten_points($bookmark), $bookmarkColor);
    imagepolygon($image, flatten_points($bookmark), $bookmarkShade);
}

function draw_cross(GdImage $image): void
{
    $goldDark = imagecolorallocatealpha($image, 214, 134, 0, 0);
    $goldMid = imagecolorallocatealpha($image, 255, 198, 52, 0);
    $goldLight = imagecolorallocatealpha($image, 255, 237, 156, 10);

    draw_glow($image, 560, 190, 165, [255, 234, 151], 6);

    imagefilledrectangle($image, 520, 60, 600, 385, $goldDark);
    imagefilledrectangle($image, 454, 132, 670, 214, $goldDark);
    imagefilledrectangle($image, 532, 76, 588, 368, $goldMid);
    imagefilledrectangle($image, 470, 144, 654, 202, $goldMid);
    imagefilledrectangle($image, 546, 92, 573, 345, $goldLight);
    imagefilledrectangle($image, 485, 158, 640, 185, $goldLight);
}

function draw_heart(GdImage $image): void
{
    $heartBase = imagecolorallocatealpha($image, 234, 24, 41, 0);
    $heartHighlight = imagecolorallocatealpha($image, 255, 102, 123, 18);
    $heartShadow = imagecolorallocatealpha($image, 140, 8, 20, 14);

    $leftCircleX = 760;
    $rightCircleX = 856;
    $circleY = 736;
    $radius = 62;

    imagefilledellipse($image, $leftCircleX, $circleY, $radius * 2, $radius * 2, $heartBase);
    imagefilledellipse($image, $rightCircleX, $circleY, $radius * 2, $radius * 2, $heartBase);

    $heartBottom = [
        [694, 760],
        [922, 760],
        [808, 902],
    ];
    imagefilledpolygon($image, flatten_points($heartBottom), $heartBase);

    imagefilledellipse($image, 780, 712, 74, 48, $heartHighlight);
    imagepolygon($image, flatten_points($heartBottom), $heartShadow);
}

function rotate_point(int $centerX, int $centerY, float $pointX, float $pointY, int $degrees): array
{
    $radians = deg2rad($degrees);
    $rotatedX = ($pointX * cos($radians)) - ($pointY * sin($radians));
    $rotatedY = ($pointX * sin($radians)) + ($pointY * cos($radians));

    return [
        (int) round($centerX + $rotatedX),
        (int) round($centerY + $rotatedY),
    ];
}

function flatten_points(array $points): array
{
    $flat = [];

    foreach ($points as [$x, $y]) {
        $flat[] = $x;
        $flat[] = $y;
    }

    return $flat;
}
