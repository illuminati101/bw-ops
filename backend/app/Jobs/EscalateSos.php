<?php

namespace App\Jobs;

use App\Models\SosAlert;
use App\Services\Safety\SosService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EscalateSos implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $sosId) {}

    public function handle(SosService $sos): void
    {
        if ($alert = SosAlert::find($this->sosId)) {
            $sos->escalate($alert);
        }
    }
}
