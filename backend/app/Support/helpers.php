<?php

use App\Support\Money;
use App\Support\Settings;

if (! function_exists('settings')) {
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key, $default);
    }
}

if (! function_exists('feature_enabled')) {
    function feature_enabled(string $feature): bool
    {
        return app(Settings::class)->enabled($feature);
    }
}

if (! function_exists('rands')) {
    function rands(float|int|string|null $amount, bool $cents = true): string
    {
        return Money::format($amount, $cents);
    }
}
