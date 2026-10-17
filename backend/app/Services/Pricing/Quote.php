<?php

namespace App\Services\Pricing;

final class Quote
{
    /**
     * @param  array<int, array{label: string, amount: float, detail?: string}>  $lines
     */
    public function __construct(
        public array $lines,
        public float $subtotal,
        public ?array $surcharge,
        public bool $minimumApplied,
        public float $total,
        public float $vatIncluded,
        public float $overtimeRate,
        public float $noShowFee,
        public float $holdAmount,
        public bool $customQuote,
        public float $estimatedKm,
        public int $durationMinutes,
    ) {}

    public function toArray(): array
    {
        return [
            'lines' => $this->lines,
            'subtotal' => $this->subtotal,
            'surcharge' => $this->surcharge,
            'minimum_applied' => $this->minimumApplied,
            'total' => $this->total,
            'vat_included' => $this->vatIncluded,
            'overtime_rate' => $this->overtimeRate,
            'no_show_fee' => $this->noShowFee,
            'hold_amount' => $this->holdAmount,
            'custom_quote' => $this->customQuote,
            'estimated_km' => $this->estimatedKm,
            'duration_minutes' => $this->durationMinutes,
            'currency' => 'ZAR',
        ];
    }
}
