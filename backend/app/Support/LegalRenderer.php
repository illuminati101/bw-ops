<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Fills {{placeholders}} in legal documents from Settings, so the policies always match the live rules. */
class LegalRenderer
{
    public static function render(string $markdown): string
    {
        $tokens = [];
        foreach (settings()->all() as $key => $value) {
            if (is_scalar($value)) {
                $tokens[$key] = (string) $value;
            }
        }
        $tokens['cancellation.no_show_minimum'] = rands(settings('cancellation.no_show_minimum'), false);
        $tokens['payments.hold_threshold'] = rands(settings('payments.hold_threshold'), false);
        $tokens['cancellation.no_show_percent'] = rtrim(rtrim(number_format((float) settings('cancellation.no_show_percent'), 2), '0'), '.');
        $tokens['cancellation.tiers_table'] = self::tiersTable();

        $markdown = preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', fn ($m) => $tokens[$m[1]] ?? $m[0], $markdown);

        return Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    public static function tiersTable(): string
    {
        $tiers = collect(settings('cancellation.tiers', []))->sortByDesc('hours')->values();
        $rows = ["| Notice before the start time | Cancellation fee |", '|---|---|'];
        foreach ($tiers as $i => $t) {
            $hours = (float) $t['hours'];
            $prev = $i > 0 ? (float) $tiers[$i - 1]['hours'] : null;
            $label = match (true) {
                $i === 0 && $hours > 0 => self::h($hours).' or more',
                $hours <= 0 => 'Less than '.self::h($prev ?? 0).($prev ? '' : ''),
                default => self::h($hours).' to '.self::h($prev),
            };
            $fee = (float) $t['percent'] <= 0 ? 'Free' : rtrim(rtrim(number_format((float) $t['percent'], 2), '0'), '.').'% of the quote';
            $rows[] = "| {$label} | {$fee} |";
        }

        return implode("\n", $rows);
    }

    private static function h(float $hours): string
    {
        if ($hours >= 24 && fmod($hours, 24) == 0) {
            $d = (int) ($hours / 24);

            return $d.' '.($d === 1 ? 'day' : 'days');
        }

        return rtrim(rtrim(number_format($hours, 1), '0'), '.').' '.($hours == 1 ? 'hour' : 'hours');
    }
}
