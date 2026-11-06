<?php

declare(strict_types=1);

if (!function_exists('ce_uuid_v4')) {
    function ce_uuid_v4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

if (!function_exists('ce_uuid')) {
    function ce_uuid(): string
    {
        return ce_uuid_v4();
    }
}
