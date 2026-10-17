<?php

namespace App\Jobs;

use App\Models\DispatchOffer;
use App\Services\Dispatch\DispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireDispatchOffer implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $offerId) {}

    public function handle(DispatchService $dispatch): void
    {
        if ($offer = DispatchOffer::find($this->offerId)) {
            $dispatch->expire($offer);
        }
    }
}
