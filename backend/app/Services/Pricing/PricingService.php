<?php

namespace App\Services\Pricing;

use App\Models\Extra;
use App\Models\Feature;
use App\Models\PublicHoliday;
use App\Models\RateCard;
use App\Models\SurchargeRule;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Turns a service's rate card into a firm quote.
 *
 * Model: each "unit" is one vehicle with its chauffeur (transport services) or one officer
 * (protection-only services). Rate-card hourly and per-km rates are per unit. Officers beyond
 * the service's default crew are added at the extra-guard rate.
 */
class PricingService
{
    public function quote(QuoteInput $in): Quote
    {
        $card = $this->rateCard($in);
        $service = $in->service;

        $shifts = $in->shifts ?: [[$in->startsAt, $in->startsAt->copy()->addMinutes($in->durationMinutes)]];
        $lines = [];
        $subtotalAll = 0.0;
        $bestSurcharge = null;
        $surchargeTotal = 0.0;
        $minimumApplied = false;
        $totalMinutes = 0;

        foreach ($shifts as $i => [$start, $end]) {
            $minutes = max($service->min_duration_minutes, (int) $start->diffInMinutes($end));
            $totalMinutes += $minutes;
            $shiftLines = $this->shiftLines($in, $card, $minutes, $i === 0);
            $shiftSubtotal = array_sum(array_column($shiftLines, 'amount'));

            $surcharge = $this->surchargeFor($start);
            $shiftSurcharge = $surcharge ? Money::round($shiftSubtotal * $surcharge['percent'] / 100) : 0.0;

            $minimum = (float) $card->minimum_charge * max(1, $in->vehicles);
            if ($shiftSubtotal + $shiftSurcharge < $minimum) {
                $minimumApplied = true;
                $shiftLines[] = ['label' => 'Minimum charge adjustment', 'amount' => Money::round($minimum - $shiftSubtotal - $shiftSurcharge)];
                $shiftSubtotal = $minimum - $shiftSurcharge;
            }

            if (count($shifts) > 1) {
                $label = 'Shift '.($i + 1).': '.$start->format('D j M, H:i').' to '.$end->format('H:i');
                $lines[] = ['label' => $label, 'amount' => Money::round($shiftSubtotal + $shiftSurcharge), 'detail' => collect($shiftLines)->pluck('label')->implode(', ')];
            } else {
                array_push($lines, ...$shiftLines);
            }

            $subtotalAll += $shiftSubtotal;
            $surchargeTotal += $shiftSurcharge;
            if ($surcharge && (! $bestSurcharge || $surcharge['percent'] > $bestSurcharge['percent'])) {
                $bestSurcharge = $surcharge;
            }
        }

        $surchargeOut = null;
        if ($surchargeTotal > 0 && count($shifts) === 1) {
            $surchargeOut = ['label' => $bestSurcharge['label'], 'percent' => $bestSurcharge['percent'], 'amount' => Money::round($surchargeTotal)];
        }

        $total = Money::roundQuote($subtotalAll + $surchargeTotal);
        $vatIncluded = settings('company.vat_registered') && settings('payments.prices_include_vat')
            ? Money::round($total * (float) settings('payments.vat_rate') / (100 + (float) settings('payments.vat_rate')))
            : 0.0;

        $overtime = $card->overtimeRate() * $this->units($in) + $this->extraGuards($in) * ($card->per_extra_guard_hour ?: $card->per_hour);
        $noShowFee = $this->noShowFee($total);
        $structure = $this->structure($in);
        $holdAmount = feature_enabled('card_holds') && ($structure !== 'single' || $total >= (float) settings('payments.hold_threshold'))
            ? $noShowFee : 0.0;

        return new Quote(
            lines: array_map(fn ($l) => ['label' => $l['label'], 'amount' => Money::round($l['amount'])] + (isset($l['detail']) ? ['detail' => $l['detail']] : []), $lines),
            subtotal: Money::round($subtotalAll),
            surcharge: $surchargeOut,
            minimumApplied: $minimumApplied,
            total: $total,
            vatIncluded: $vatIncluded,
            overtimeRate: Money::round($overtime),
            noShowFee: $noShowFee,
            holdAmount: $holdAmount,
            customQuote: (bool) $service->custom_quote,
            estimatedKm: round($in->distanceKm, 1),
            durationMinutes: $totalMinutes,
        );
    }

    public function noShowFee(float $total): float
    {
        if (! feature_enabled('no_show_fee')) {
            return 0.0;
        }

        return Money::round(max((float) settings('cancellation.no_show_minimum'), $total * (float) settings('cancellation.no_show_percent') / 100));
    }

    public function structure(QuoteInput $in): string
    {
        if (count($in->shifts) > 1) {
            return 'multi_day';
        }
        if ($in->vehicles > 1 || $in->guards > max(1, $in->service->default_guards) || $in->service->structure === 'team') {
            return 'team';
        }

        return 'single';
    }

    private function rateCard(QuoteInput $in): RateCard
    {
        $card = $in->service->rateCards()->where('region_id', $in->region->id)->first();
        if (! $card || (! $in->useDraftPricing && ! $card->is_published)) {
            throw ValidationException::withMessages(['service' => 'This service is not yet priced for '.$in->region->name.'.']);
        }

        return $in->useDraftPricing ? $card->withDraftApplied() : $card;
    }

    private function units(QuoteInput $in): int
    {
        return $in->service->includes_transport ? max(1, $in->vehicles) : max(1, $in->guards);
    }

    private function extraGuards(QuoteInput $in): int
    {
        if (! $in->service->includes_transport) {
            return 0; // protection-only: every officer is a unit
        }
        $included = $in->service->default_guards * max(1, $in->vehicles);

        return max(0, $in->guards - $included);
    }

    private function shiftLines(QuoteInput $in, RateCard $card, int $minutes, bool $firstShift): array
    {
        $hours = $minutes / 60;
        $units = $this->units($in);
        $lines = [];
        $service = $in->service;

        if ($card->callout_fee > 0) {
            $lines[] = ['label' => 'Call-out fee'.($units > 1 ? " × {$units}" : ''), 'amount' => $card->callout_fee * $units];
        }

        $unitWord = $service->includes_transport ? ($units > 1 ? "{$units} vehicles with chauffeurs" : 'Vehicle and chauffeur') : ($units > 1 ? "{$units} protection officers" : 'Protection officer');
        if ($service->includes_transport && $service->includes_protection) {
            $unitWord = $units > 1 ? "{$units} vehicles, each with chauffeur and officer" : 'Chauffeur and protection officer';
        }
        $lines[] = ['label' => $unitWord.', '.$this->hoursLabel($hours), 'amount' => $card->per_hour * $hours * $units];

        if ($extra = $this->extraGuards($in)) {
            $rate = $card->per_extra_guard_hour ?: $card->per_hour;
            $lines[] = ['label' => ($extra > 1 ? "{$extra} additional officers, " : 'Additional officer, ').$this->hoursLabel($hours), 'amount' => $rate * $hours * $extra];
        }

        if ($service->includes_transport && $firstShift && $in->distanceKm > 0) {
            $chargeable = max(0, $in->distanceKm - $card->included_km);
            if ($chargeable > 0 && $card->per_km > 0) {
                $lines[] = ['label' => 'Distance beyond included, '.number_format($chargeable, 1).' km'.($units > 1 ? " × {$units}" : ''), 'amount' => $card->per_km * $chargeable * $units];
            }
        }

        // Feature surcharges, e.g. "Armed operator"
        if ($in->featureIds) {
            foreach (Feature::query()->whereIn('id', $in->featureIds)->where('surcharge_type', '!=', 'none')->get() as $f) {
                $amount = $f->surcharge_type === 'per_hour' ? $f->surcharge_amount * $hours * $units : $f->surcharge_amount * $units;
                if ($amount > 0) {
                    $lines[] = ['label' => $f->labelForClients(), 'amount' => (float) $amount];
                }
            }
        }

        // Extras
        if ($in->extras) {
            $extraModels = Extra::query()->whereIn('id', array_column($in->extras, 'id'))->get()->keyBy('id');
            $base = array_sum(array_column($lines, 'amount'));
            foreach ($in->extras as $sel) {
                $extra = $extraModels[$sel['id']] ?? null;
                if (! $extra) {
                    continue;
                }
                $extra = $in->useDraftPricing ? $extra->withDraftApplied() : $extra;
                $qty = max(1, (int) ($sel['quantity'] ?? 1));
                $amount = match ($extra->pricing) {
                    'per_hour' => $extra->amount * $hours * $qty,
                    'percent' => $base * $extra->amount / 100,
                    default => $extra->amount * $qty,
                };
                if (! $firstShift && $extra->pricing === 'flat') {
                    continue; // flat extras are charged once per booking
                }
                $lines[] = ['label' => $extra->name.($qty > 1 ? " × {$qty}" : ''), 'amount' => (float) $amount];
            }
        }

        return $lines;
    }

    /** @return array{label: string, percent: float}|null The single highest applicable surcharge. */
    public function surchargeFor(CarbonInterface $start): ?array
    {
        $best = null;
        foreach (SurchargeRule::query()->where('is_active', true)->get() as $rule) {
            $applies = match ($rule->kind) {
                'holiday' => PublicHoliday::query()->whereDate('date', $start->toDateString())->exists(),
                'weekend' => $start->isWeekend(),
                'night' => $this->inWindow($start, $rule->starts_at, $rule->ends_at),
                default => false,
            };
            if ($applies && (! $best || $rule->percent > $best['percent'])) {
                $best = ['label' => $rule->name, 'percent' => (float) $rule->percent];
            }
        }

        return $best;
    }

    private function inWindow(CarbonInterface $at, ?string $from, ?string $to): bool
    {
        if (! $from || ! $to) {
            return false;
        }
        $t = $at->format('H:i:s');
        $from = strlen($from) === 5 ? $from.':00' : $from;
        $to = strlen($to) === 5 ? $to.':00' : $to;

        return $from <= $to ? ($t >= $from && $t < $to) : ($t >= $from || $t < $to);
    }

    private function hoursLabel(float $hours): string
    {
        $h = floor($hours);
        $m = round(($hours - $h) * 60);

        return trim(($h ? $h.' hour'.($h == 1 ? '' : 's') : '').($m ? ' '.$m.' min' : ''));
    }
}
