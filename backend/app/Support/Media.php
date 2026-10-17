<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class Media
{
    /** Bundled art lives in public/images; uploads from the admin panel live on the public disk. */
    public static function url(?string $path, ?string $fallback = null): ?string
    {
        $path = $path ?: $fallback;
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        if (str_starts_with($path, 'images/')) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }
}
