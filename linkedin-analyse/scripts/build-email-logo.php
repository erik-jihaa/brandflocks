<?php

declare(strict_types=1);

/** Genereer logo-email.png: strak logo op #EAE6DC — geen witte/zwarte rand in Mail. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$assets = dirname(__DIR__) . '/assets';
$srcPath = $assets . '/iGiSNext_Blue.png';
$outPath = $assets . '/logo-email.png';

if (!is_readable($srcPath)) {
    fwrite(STDERR, "Bron niet gevonden: {$srcPath}\n");
    exit(1);
}

$src = imagecreatefrompng($srcPath);
if ($src === false) {
    fwrite(STDERR, "PNG laden mislukt.\n");
    exit(1);
}

$w = imagesx($src);
$h = imagesy($src);

$isBackground = static function (int $r, int $g, int $b, int $a): bool {
    if ($a < 16) {
        return true;
    }
    if ($r < 30 && $g < 30 && $b < 30) {
        return true;
    }
    if ($r > 240 && $g > 240 && $b > 240) {
        return true;
    }
    if (abs($r - 234) < 20 && abs($g - 230) < 20 && abs($b - 220) < 25) {
        return true;
    }

    return false;
};

$minX = $w;
$minY = $h;
$maxX = 0;
$maxY = 0;

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $rgba = imagecolorat($src, $x, $y);
        $a = ($rgba >> 24) & 0x7F;
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;
        if (!$isBackground($r, $g, $b, 255 - $a)) {
            $minX = min($minX, $x);
            $minY = min($minY, $y);
            $maxX = max($maxX, $x);
            $maxY = max($maxY, $y);
        }
    }
}

if ($maxX <= $minX || $maxY <= $minY) {
    fwrite(STDERR, "Geen logo-inhoud gevonden.\n");
    exit(1);
}

$cropW = $maxX - $minX + 1;
$cropH = $maxY - $minY + 1;
$crop = imagecreatetruecolor($cropW, $cropH);
imagealphablending($crop, false);
imagesavealpha($crop, true);

for ($y = 0; $y < $cropH; $y++) {
    for ($x = 0; $x < $cropW; $x++) {
        $col = imagecolorat($src, $minX + $x, $minY + $y);
        imagesetpixel($crop, $x, $y, $col);
    }
}
imagedestroy($src);

$targetW = 180;
$targetH = max(1, (int) round($cropH * ($targetW / $cropW)));
$scaled = imagecreatetruecolor($targetW, $targetH);
imagealphablending($scaled, false);
imagesavealpha($scaled, true);
imagecopyresampled($scaled, $crop, 0, 0, 0, 0, $targetW, $targetH, $cropW, $cropH);
imagedestroy($crop);

$beige = imagecolorallocate($scaled, 234, 230, 220);
$out = imagecreatetruecolor($targetW, $targetH);
imagefilledrectangle($out, 0, 0, $targetW, $targetH, $beige);

for ($y = 0; $y < $targetH; $y++) {
    for ($x = 0; $x < $targetW; $x++) {
        $rgba = imagecolorat($scaled, $x, $y);
        $a = ($rgba >> 24) & 0x7F;
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;
        if ($isBackground($r, $g, $b, 255 - $a)) {
            continue;
        }
        $alpha = (255 - $a) / 255;
        $rr = (int) round($r * $alpha + 234 * (1 - $alpha));
        $gg = (int) round($g * $alpha + 230 * (1 - $alpha));
        $bb = (int) round($b * $alpha + 220 * (1 - $alpha));
        $col = imagecolorallocate($out, $rr, $gg, $bb);
        imagesetpixel($out, $x, $y, $col);
    }
}
imagedestroy($scaled);

if (!imagepng($out, $outPath, 9)) {
    fwrite(STDERR, "Schrijven mislukt.\n");
    exit(1);
}
imagedestroy($out);

echo "OK: {$outPath} ({$targetW}x{$targetH})\n";
