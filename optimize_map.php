<?php
/**
 * Image Downscaler for ACCSESS Map
 * Resizes the 14MB map to high quality WebP and JPG for blazing fast rendering.
 */

$source = __DIR__ . '/img/airport_map.jpg';
$targetWebp = __DIR__ . '/img/airport_map_opt.webp';
$targetJpg = __DIR__ . '/img/airport_map_opt.jpg';
$targetOpt = __DIR__ . '/img/airport_map_optimized.jpg';
$maxWidth = 4096;

if (!file_exists($source)) {
    die("Source file not found: $source\n");
}

ini_set('memory_limit', '2048M');
set_time_limit(120);
list($width, $height, $type) = getimagesize($source);
echo "Original size: {$width}x{$height}\n";

if ($width > $maxWidth) {
    $newWidth = $maxWidth;
    $newHeight = (int)(($height / $width) * $maxWidth);
} else {
    $newWidth = $width;
    $newHeight = $height;
}

echo "New size: {$newWidth}x{$newHeight}\n";

$srcImg = imagecreatefromjpeg($source);
$dstImg = imagecreatetruecolor($newWidth, $newHeight);

// Resample with high quality
imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

// Save WebP
if (function_exists('imagewebp')) {
    imagewebp($dstImg, $targetWebp, 88);
    echo "WebP saved: $targetWebp (" . filesize($targetWebp) . " bytes)\n";
}

// Save JPG
imagejpeg($dstImg, $targetJpg, 88);
echo "JPG saved: $targetJpg (" . filesize($targetJpg) . " bytes)\n";

copy($targetJpg, $targetOpt);

imagedestroy($srcImg);
imagedestroy($dstImg);

echo "Success!\n";
?>
