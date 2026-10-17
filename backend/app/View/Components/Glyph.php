<?php

namespace App\View\Components;

use Illuminate\View\Component;

/** Inline Lucide icon (ISC licence) from resources/svg/icons. */
class Glyph extends Component
{
    private const ALIASES = ['steering-wheel' => 'route', 'bar-chart' => 'bar-chart-3', 'shield-check' => 'shield-check'];

    public function __construct(public string $name, public string $class = 'size-5', public string $stroke = '1.5') {}

    public function render(): string
    {
        static $cache = [];
        $name = self::ALIASES[$this->name] ?? $this->name;
        $path = resource_path("svg/icons/{$name}.svg");
        if (! is_file($path)) {
            $path = resource_path('svg/icons/shield.svg');
        }
        // Strip size/class from the <svg> tag only: inner <rect> elements need their width/height.
        $svg = $cache[$path] ??= preg_replace_callback('/<svg\b[^>]*>/', fn ($m) => preg_replace('/\s(width|height|class)="[^"]*"/', '', $m[0]), preg_replace('/<!--.*?-->/s', '', file_get_contents($path)));
        $svg = preg_replace('/stroke-width="[^"]*"/', 'stroke-width="'.e($this->stroke).'"', $svg);

        return str_replace('<svg', '<svg aria-hidden="true" focusable="false" class="'.e($this->class).'"', trim($svg));
    }
}
