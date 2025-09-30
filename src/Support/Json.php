<?php
declare(strict_types=1);

namespace BailaYa\Support;

final class Json
{
    /** @return array<string,string> */
    public static function mapOrEmpty(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? array_map('strval', $decoded) : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
