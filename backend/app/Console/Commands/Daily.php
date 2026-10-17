<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\LocationPing;
use App\Models\Message;
use App\Models\OtpCode;
use App\Services\Messaging\Notifier;
use Illuminate\Console\Command;

class Daily extends Command
{
    protected $signature = 'bw:daily';

    protected $description = 'Daily compliance reminders and data-retention clean-up (POPIA)';

    public function handle(Notifier $notifier): int
    {
        // Compliance reminders (each document type has its own reminder window)
        Document::query()->with('type', 'documentable')->where('status', 'approved')->whereNotNull('expires_on')->whereNull('reminder_sent_at')
            ->get()->filter(fn ($d) => $d->expires_on->lte(today()->addDays($d->type->reminder_days ?? 30)))
            ->each(function (Document $d) use ($notifier) {
                $notifier->documentExpiring($d);
                $d->update(['reminder_sent_at' => now()]);
            });

        // Retention periods from the privacy policy
        LocationPing::query()->where('recorded_at', '<', now()->subDays(90))
            ->whereNotIn('booking_id', \App\Models\SosAlert::query()->whereNotNull('booking_id')->pluck('booking_id'))
            ->delete();
        Message::query()->where('created_at', '<', now()->subMonths(12))->delete();
        OtpCode::query()->where('created_at', '<', now()->subDays(7))->delete();
        \App\Models\OutboundMessage::query()->where('sensitive', true)->where('created_at', '<', now()->subDays(30))->update(['body' => '[removed]']);

        Invoice::query()->where('status', 'sent')->whereDate('due_on', '<', today())->update(['status' => 'overdue']);

        $this->info('Daily tasks complete.');

        return self::SUCCESS;
    }
}
