<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Settings
{
    private const CACHE_KEY = 'bwops.settings.v1';

    private ?array $values = null;

    public function definitions(): array
    {
        return config('bwops.settings', []);
    }

    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $defaults = array_map(fn ($def) => $def[0], $this->definitions());

        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Setting::query()->pluck('value', 'key')->all();
        });

        return $this->values = array_merge($defaults, $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key, 0);
    }

    public function float(string $key): float
    {
        return (float) $this->get($key, 0);
    }

    public function enabled(string $feature): bool
    {
        return $this->bool('features.'.$feature);
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }

    /** Values grouped for the admin settings page. */
    public function grouped(): array
    {
        $out = [];
        foreach ($this->definitions() as $key => $def) {
            $out[$def[2]][$key] = $def;
        }

        return $out;
    }
}
