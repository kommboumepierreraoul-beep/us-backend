<?php

if (! function_exists('normalize_env_reference')) {
    function normalize_env_reference(?string $value, ?string $fallback = null): ?string
    {
        if ($value === null || $value === '' || str_contains($value, '${')) {
            return $fallback;
        }

        return $value;
    }
}
