<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $map = [
        'Dompdf\\' => __DIR__ . '/dompdf/src/',
        'FontLib\\' => __DIR__ . '/php-font-lib/src/FontLib/',
        'Svg\\' => __DIR__ . '/php-svg-lib/src/Svg/',
        'Sabberworm\\CSS\\' => __DIR__ . '/php-css-parser/src/',
        'Masterminds\\' => __DIR__ . '/masterminds-html5/src/',
        'Smalot\\PdfParser\\' => __DIR__ . '/smalot-pdfparser/src/Smalot/PdfParser/',
    ];

    foreach ($map as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_readable($file)) {
            require_once $file;
        }

        return;
    }
});

require_once __DIR__ . '/dompdf/lib/Cpdf.php';
