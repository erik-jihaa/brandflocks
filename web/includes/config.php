<?php

declare(strict_types=1);

/**
 * Base URL path for assets and links.
 * Auto-detects /erik-ce when document root is the parent folder.
 * After cPanel document root → erik-ce, this becomes '' and URLs stay /assets/...
 */
function ce_base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = str_replace('\\', '/', dirname($script));

    if ($dir === '/' || $dir === '.') {
        $base = '';
    } else {
        $base = rtrim($dir, '/');
    }

    return $base;
}

function ce_url(string $path = ''): string
{
    $path = $path === '' ? '/' : '/' . ltrim($path, '/');
    return ce_base_url() . $path;
}
