<?php

namespace App\Services\Pricing;

use App\Models\Region;
use App\Models\Service;
use Carbon\CarbonInterface;

final class QuoteInput
{
    /**
     * @param  array<int, array{id: int, quantity?: int}>  $extras
     * @param  array<int, int>  $featureIds
     * @param  array<int, array{0: CarbonInterface, 1: CarbonInterface}>  $shifts  for multi-day / recurring details
     */
    public function __construct(
        public Service $service,
        public Region $region,
        public string $mode,
        public CarbonInterface $startsAt,
        public int $durationMinutes,
        public float $distanceKm = 0,
        public int $guards = 0,
        public int $vehicles = 1,
        public array $extras = [],
        public array $featureIds = [],
        public int $stops = 0,
        public array $shifts = [],
        public bool $useDraftPricing = false,
    ) {}
}
