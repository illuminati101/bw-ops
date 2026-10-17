<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Events\ControlRoomAlert;
use App\Models\Booking;
use App\Models\DispatchOffer;
use App\Models\Extra;
use App\Models\Faq;
use App\Models\Page;
use App\Models\RateCard;
use App\Models\Service;
use App\Services\Dispatch\DispatchService;
use App\Services\Messaging\Notifier;
use App\Support\Realtime;
use Illuminate\Console\Command;

/** Runs every minute: scheduled publishing, reconfirmations, dispatch watchdogs and escalations. */
class Tick extends Command
{
    protected $signature = 'bw:tick';

    protected $description = 'Minute-by-minute housekeeping for bookings, dispatch and publishing';

    public function handle(DispatchService $dispatch, Notifier $notifier): int
    {
        // 1. Scheduled publishing
        foreach ([Service::class, RateCard::class, Extra::class, Page::class, Faq::class] as $model) {
            $model::query()->whereNotNull('publish_at')->where('publish_at', '<=', now())->each(fn ($m) => $m->publish('Scheduled publish'));
        }

        // 2. Expired offers whose delayed job hasn't run (e.g. queue worker restarted)
        DispatchOffer::query()->where('status', 'pending')->where('expires_at', '<', now()->subSeconds(5))->each(fn ($o) => $dispatch->expire($o));

        // 3. On-demand bookings stuck searching
        $escalateAfter = (int) settings('escalation.unassigned_minutes');
        Booking::query()->where('status', BookingStatus::Searching->value)->where('created_at', '<', now()->subMinutes($escalateAfter))
            ->whereNull('oncall_alerted_at')->each(function (Booking $b) use ($notifier) {
                $b->update(['oncall_alerted_at' => now()]);
                $notifier->escalateToOnCall('On-demand job unassigned', "{$b->reference} has had no operator for ".settings('escalation.unassigned_minutes').' minutes.', 1);
            });
        Booking::query()->where('status', BookingStatus::Searching->value)->where('created_at', '<', now()->subMinutes(max(20, $escalateAfter * 3)))
            ->each(function (Booking $b) use ($dispatch, $notifier) {
                $dispatch->cancelAllOffers($b);
                $b->update(['status' => BookingStatus::Expired]);
                $b->logEvent('dispatch.timeout', 'No operator found in time: booking closed without charge');
                app(\App\Services\Payments\PaymentService::class)->releaseHold($b);
                $notifier->noOperatorFound($b);
            });

        // 4. On-demand requests waiting for approval need fast attention
        Booking::query()->where('status', BookingStatus::PendingApproval->value)->where('mode', 'on_demand')
            ->where('created_at', '<', now()->subMinutes(2))->whereNull('oncall_alerted_at')->each(function (Booking $b) use ($notifier) {
                $b->update(['oncall_alerted_at' => now()]);
                $notifier->escalateToOnCall('On-demand request awaiting approval', "{$b->reference} from {$b->client->name} is waiting.", 1);
            });

        // 5. Reconfirmation: ask the client 24h before (or now if closer), alert staff at the deadline
        if (feature_enabled('reconfirmation')) {
            Booking::query()->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Assigned->value])
                ->whereNotNull('reconfirm_due_at')->whereNull('reconfirmed_at')
                ->where('starts_at', '<=', now()->addHours(24 + (int) settings('booking.reconfirm_hours')))
                ->whereDoesntHave('events', fn ($q) => $q->where('type', 'reconfirm.requested'))
                ->each(function (Booking $b) use ($notifier) {
                    $b->logEvent('reconfirm.requested', 'Reconfirmation requested from client');
                    $notifier->reconfirmRequest($b);
                });
            Booking::query()->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Assigned->value])
                ->whereNotNull('reconfirm_due_at')->whereNull('reconfirmed_at')->whereNull('reconfirm_alerted_at')
                ->where('reconfirm_due_at', '<=', now())
                ->each(function (Booking $b) use ($notifier) {
                    $b->update(['reconfirm_alerted_at' => now()]);
                    $b->logEvent('reconfirm.missed', 'Client did not reconfirm by the deadline');
                    Realtime::send(new ControlRoomAlert('reconfirm', 'Not reconfirmed: '.$b->reference, 'Call the client. You may release the booking.', ['booking_id' => $b->id]));
                    $notifier->staff('Booking not reconfirmed', "{$b->reference} ({$b->client->name}) has not reconfirmed. Call the client or release it.", 'bookings');
                });
        }

        // 6. Confirmed advance bookings still without operators close to start
        $hours = (int) settings('escalation.advance_unassigned_hours');
        Booking::query()->where('status', BookingStatus::Confirmed->value)->where('mode', 'advance')
            ->where('starts_at', '<=', now()->addHours($hours))->whereNull('escalated_at')
            ->each(function (Booking $b) use ($notifier, $hours) {
                $b->update(['escalated_at' => now()]);
                Realtime::send(new ControlRoomAlert('unassigned', 'Unassigned: '.$b->reference, "Starts within {$hours}h with open operator slots.", ['booking_id' => $b->id]));
                $notifier->staff('Booking needs operators', "{$b->reference} starts {$b->starts_at->format('D H:i')} and still has open slots.", 'dispatch');
            });

        return self::SUCCESS;
    }
}
