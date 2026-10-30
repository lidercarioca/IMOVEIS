<?php
function reencode_image(string $sourcePath, string $destPath, string $mime): bool
{
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $img = @imagecreatefromjpeg($sourcePath);
            if (!$img) return false;
            // Re-encode with quality 85
            $result = imagejpeg($img, $destPath, 85);
            imagedestroy($img);
            return $result;
        case 'image/png':
            $img = @imagecreatefrompng($sourcePath);
            if (!$img) return false;
            imagealphablending($img, false);
            imagesavealpha($img, true);
            // quality 6 (0-9)
            $result = imagepng($img, $destPath, 6);
            imagedestroy($img);
            return $result;
        case 'image/gif':
            $img = @imagecreatefromgif($sourcePath);
            if (!$img) return false;
            $result = imagegif($img, $destPath);
            imagedestroy($img);
            return $result;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) return false;
            $img = @imagecreatefromwebp($sourcePath);
            if (!$img) return false;
            $result = imagewebp($img, $destPath, 80);
            imagedestroy($img);
            return $result;
        default:
            return false;
    }
}
