<?php
/**
 * Image Downscaler for ACCSESS Map
 * Resizes the 14MB map to a more manageable size.
 */

$source = __DIR__ . '/img/airport_map.jpg';
$target = __DIR__ . '/img/airport_map_optimized.jpg';
$maxWidth = 4096;

if (!file_exists($source)) {
    die("Source file not found: $source\n");
}

list($width, $height, $type) = getimagesize($source);
echo "Original size: {$width}x{$height}\n";

if ($width > $maxWidth) {
    $newWidth = $maxWidth;
    $newHeight = ($height / $width) * $maxWidth;
} else {
    echo "Already smaller than maxWidth. Skipping.\n";
    copy($source, $target);
    exit;
}

echo "New size: {$newWidth}x{$newHeight}\n";

$srcImg = imagecreatefromjpeg($source);
$dstImg = imagecreatetruecolor($newWidth, $newHeight);

// Resample
imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

// Save with 85% quality
imagejpeg($dstImg, $target, 85);

imagedestroy($srcImg);
imagedestroy($dstImg);

echo "Optimized image saved to: $target\n";
echo "Original size: " . filesize($source) . " bytes\n";
echo "New size: " . filesize($target) . " bytes\n";
?>
