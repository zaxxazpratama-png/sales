<?php
namespace App;

class ImageHelper
{
    /**
     * Konversi gambar tanda tangan (JPG/PNG dengan background kertas putih)
     * menjadi PNG transparan dengan goresan tinta hitam tajam
     *
     * @param string $sourcePath Path file input
     * @param string $destPath   Path file output PNG
     * @param int    $threshold  Ambang batas kecerahan (0-255)
     * @return bool
     */
    public static function makeTransparentSignature(string $sourcePath, string $destPath, int $threshold = 215): bool
    {
        if (!file_exists($sourcePath)) {
            return false;
        }

        // Cek jika ekstensi GD tersedia
        if (extension_loaded('gd')) {
            $info = @getimagesize($sourcePath);
            if (!$info) return false;

            $mime = $info['mime'];
            switch ($mime) {
                case 'image/jpeg':
                case 'image/jpg':
                    $srcImg = @imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $srcImg = @imagecreatefrompng($sourcePath);
                    break;
                case 'image/webp':
                    $srcImg = @imagecreatefromwebp($sourcePath);
                    break;
                default:
                    return false;
            }

            if (!$srcImg) return false;

            $width  = imagesx($srcImg);
            $height = imagesy($srcImg);

            // Buat canvas baru untuk RGBA transparan
            $outImg = imagecreatetruecolor($width, $height);
            imagealphablending($outImg, false);
            imagesavealpha($outImg, true);

            $transparent = imagecolorallocatealpha($outImg, 0, 0, 0, 127);
            imagefilledrectangle($outImg, 0, 0, $width, $height, $transparent);

            $minX = $width; $minY = $height; $maxX = 0; $maxY = 0;
            $hasContent = false;

            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $rgb = imagecolorat($srcImg, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    $lum = (int)(($r * 299 + $g * 587 + $b * 114) / 1000);

                    if ($lum > $threshold) {
                        imagesetpixel($outImg, $x, $y, $transparent);
                    } else {
                        // Hitung alpha antialiased (0 = opaque, 127 = full transparent di GD)
                        $norm = 1.0 - ($lum / (float)$threshold);
                        $alphaVal = (int)(255 * pow($norm, 0.65));
                        $alphaVal = max(0, min(255, $alphaVal));
                        // Konversi 0..255 alpha scale ke 127..0 scale GD
                        $gdAlpha = 127 - (int)($alphaVal * 127 / 255);

                        $color = imagecolorallocatealpha($outImg, 15, 23, 42, $gdAlpha);
                        imagesetpixel($outImg, $x, $y, $color);

                        $hasContent = true;
                        if ($x < $minX) $minX = $x;
                        if ($x > $maxX) $maxX = $x;
                        if ($y < $minY) $minY = $y;
                        if ($y > $maxY) $maxY = $y;
                    }
                }
            }

            imagedestroy($srcImg);

            // Crop jika ada konten
            $finalImg = $outImg;
            if ($hasContent && ($maxX > $minX) && ($maxY > $minY)) {
                $pad = 4;
                $cropX = max(0, $minX - $pad);
                $cropY = max(0, $minY - $pad);
                $cropW = min($width - $cropX, ($maxX - $minX) + ($pad * 2));
                $cropH = min($height - $cropY, ($maxY - $minY) + ($pad * 2));

                $cropped = imagecrop($outImg, ['x' => $cropX, 'y' => $cropY, 'width' => $cropW, 'height' => $cropH]);
                if ($cropped !== false) {
                    imagedestroy($outImg);
                    $finalImg = $cropped;
                }
            }

            $dir = dirname($destPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            imagealphablending($finalImg, false);
            imagesavealpha($finalImg, true);
            $success = imagepng($finalImg, $destPath, 9);
            imagedestroy($finalImg);
            return $success;
        }

        // Fallback jika GD tidak aktif: salin file langsung
        return copy($sourcePath, $destPath);
    }
}
