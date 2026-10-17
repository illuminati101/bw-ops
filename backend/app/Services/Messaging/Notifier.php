<?php

namespace App\Services\Messaging;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\DispatchOffer;
use App\Models\Document;
use App\Models\OnCallShift;
use App\Models\SosAlert;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;

/**
 * Every customer, operator and staff message in one place, so wording stays consistent.
 * Push (apps) and email go for everything; SMS only for critical moments, per the brief.
 */
class Notifier
{
    public function __construct(private Channels $channels) {}

    private function brand(): string
    {
        return (string) settings('company.trading_name', 'Black & White Ops');
    }

    private function url(string $path): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }

    private function when(Booking $b): string
    {
        return $b->starts_at->timezone(config('app.timezone'))->format('D j M Y, H:i');
    }

    // ── Auth ─────────────────────────────────────────────────────────────

    public function otp(string $phone, string $code): void
    {
        $this->channels->sms($phone, "{$code} is your {$this->brand()} login code. It expires in 10 minutes. Never share it with anyone, including our staff.", null, true);
    }

    // ── Client booking lifecycle ─────────────────────────────────────────

    public function bookingReceived(Booking $b): void
    {
        $c = $b->client;
        $status = $b->status->value;
        [$title, $line] = match ($status) {
            'quote_requested' => ['Quote request received', "We've received your request for {$b->service->name} on {$this->when($b)}. Our team will send you a tailored quote shortly."],
            'pending_approval' => ['Booking received', "Thank you. Your {$b->service->name} booking for {$this->when($b)} is with our team for a quick review. We'll confirm shortly."],
            'searching' => ['Finding your operator', 'We are matching you with the nearest available operator now.'],
            default => ['Booking confirmed', "Your {$b->service->name} booking for {$this->when($b)} is confirmed. We'll let you know who your operator is closer to the time."],
        };
        $this->channels->push($c, $title, $line, ['booking' => $b->reference], 'client');
        if ($status !== 'searching') {
            $this->channels->email($c, new BrandedMail(
                "{$title} · {$b->reference}",
                "Hello {$c->firstName()},",
                [$line],
                'View booking',
                $this->url("account/bookings/{$b->reference}"),
                $this->bookingTable($b),
            ));
        }
    }

    public function bookingConfirmed(Booking $b): void
    {
        $c = $b->client;
        $line = "Your {$b->service->name} booking for {$this->when($b)} is confirmed.";
        $this->channels->push($c, 'Booking confirmed', $line, ['booking' => $b->reference], 'client');
        $this->channels->email($c, new BrandedMail("Booking confirmed · {$b->reference}", "Hello {$c->firstName()},", [$line, 'Nothing is charged now. Your saved card is charged once the job is complete.'], 'View booking', $this->url("account/bookings/{$b->reference}"), $this->bookingTable($b)));
    }

    public function quoteReady(Booking $b): void
    {
        $c = $b->client;
        $line = "Your tailored quote for {$b->service->name} on {$this->when($b)} is ready: ".rands($b->quoted_total).'. Accept it in the app or on our website to confirm.';
        $this->channels->push($c, 'Your quote is ready', $line, ['booking' => $b->reference], 'client');
        $this->channels->email($c, new BrandedMail("Your quote · {$b->reference}", "Hello {$c->firstName()},", [$line], 'Review and accept', $this->url("account/bookings/{$b->reference}"), $this->bookingTable($b)));
    }

    public function bookingDeclined(Booking $b, ?string $reason): void
    {
        $c = $b->client;
        $line = "We're sorry, we can't take your booking for {$this->when($b)}.".($reason ? " {$reason}" : '').' You have not been charged.';
        $this->channels->push($c, 'Booking update', $line, ['booking' => $b->reference], 'client');
        $this->channels->email($c, new BrandedMail("Booking update · {$b->reference}", "Hello {$c->firstName()},", [$line, 'Please contact us if you would like help finding an alternative.']));
    }

    public function operatorAssigned(Booking $b): void
    {
        $c = $b->client;
        $names = $b->activeAssignments()->with('operator.user', 'vehicle')->get()->map(fn ($a) => $a->operator->displayName().' ('.$a->roleLabel().')')->implode(', ');
        $vehicle = $b->activeAssignments()->whereNotNull('vehicle_id')->with('vehicle')->first()?->vehicle;
        $line = "Your operator: {$names}.".($vehicle ? " Vehicle: {$vehicle->label()}." : '');
        if (feature_enabled('arrival_pin')) {
            $line .= " Your arrival PIN is {$b->arrival_pin}. Only get in once they've told you this PIN.";
        }
        $this->channels->push($c, 'Operator assigned', $line, ['booking' => $b->reference], 'client');
        $this->notifyPrincipal($b, "{$this->brand()}: your operator for {$this->when($b)} is {$names}.".($vehicle ? " Vehicle {$vehicle->label()}." : '').(feature_enabled('arrival_pin') ? " PIN: {$b->arrival_pin}." : '').' Track: '.$this->principalLink($b));
    }

    public function operatorEnRoute(Booking $b, ?int $etaMinutes): void
    {
        $line = 'Your operator is on the way'.($etaMinutes ? ", about {$etaMinutes} min away." : '.');
        $this->channels->push($b->client, 'On the way', $line, ['booking' => $b->reference], 'client');
    }

    public function operatorArrived(Booking $b): void
    {
        $line = 'Your operator has arrived at the pickup point.'.(feature_enabled('arrival_pin') ? ' Confirm your PIN before getting in.' : '');
        $this->channels->push($b->client, 'Operator arrived', $line, ['booking' => $b->reference], 'client');
        // Critical moment: SMS too.
        $phone = $b->principal_phone ?: $b->client->phone;
        if ($phone) {
            $this->channels->sms($phone, "{$this->brand()}: your operator has arrived.".(feature_enabled('arrival_pin') ? " Ask for PIN {$b->arrival_pin} before getting in." : ''), $b->client);
        }
    }

    public function bookingCompleted(Booking $b): void
    {
        $c = $b->client;
        $total = rands($b->final_total ?? $b->quoted_total);
        $paid = match ($b->payment_status) {
            'paid' => "We've charged {$total} to your saved card.",
            'invoiced' => "{$total} will appear on your next invoice.",
            default => "Total due: {$total}.",
        };
        $this->channels->push($c, 'Job complete', "Thank you for travelling with us. {$paid}", ['booking' => $b->reference, 'rate' => true], 'client');
        $table = $this->bookingTable($b);
        foreach ($b->quote_breakdown['lines'] ?? [] as $l) {
            $table[] = ['label' => $l['label'], 'value' => rands($l['amount'])];
        }
        if ($b->overtime_minutes > 0) {
            $table[] = ['label' => "Overtime ({$b->overtime_minutes} min)", 'value' => rands(($b->final_total ?? 0) - $b->quoted_total)];
        }
        $table[] = ['label' => 'Total', 'value' => $total];
        $this->channels->email($c, new BrandedMail("Receipt · {$b->reference}", "Hello {$c->firstName()},", ["Thank you for choosing {$this->brand()}. {$paid}"], 'Rate your operator', $this->url("account/bookings/{$b->reference}"), $table, ['This receipt is also available in your account at any time.']));
    }

    public function bookingCancelled(Booking $b, float $fee, bool $byUs): void
    {
        $c = $b->client;
        $line = $byUs
            ? "We're sorry, we've had to cancel your booking for {$this->when($b)}. You will not be charged."
            : "Your booking for {$this->when($b)} has been cancelled.".($fee > 0 ? ' A cancellation fee of '.rands($fee).' applies, in line with our cancellation policy.' : ' No fee applies.');
        $this->channels->push($c, 'Booking cancelled', $line, ['booking' => $b->reference], 'client');
        $this->channels->email($c, new BrandedMail("Booking cancelled · {$b->reference}", "Hello {$c->firstName()},", [$line]));
        foreach ($b->activeAssignments()->with('operator.user')->get() as $a) {
            $this->channels->push($a->operator->user, 'Job cancelled', "{$b->reference} on {$this->when($b)} has been cancelled.", ['job' => $b->id], 'crew');
        }
    }

    public function noShowRecorded(Booking $b, float $fee): void
    {
        $line = "We couldn't reach you at the pickup point, so your booking for {$this->when($b)} was recorded as a no-show.".($fee > 0 ? ' A fee of '.rands($fee).' applies.' : '');
        $this->channels->push($b->client, 'Missed booking', $line, ['booking' => $b->reference], 'client');
        $this->channels->email($b->client, new BrandedMail("Missed booking · {$b->reference}", "Hello {$b->client->firstName()},", [$line, 'If something went wrong on our side, reply to this email and a person will review it.']));
    }

    public function reconfirmRequest(Booking $b): void
    {
        $line = "Please reconfirm your booking for {$this->when($b)} in the app or on our website.";
        $this->channels->push($b->client, 'Please reconfirm', $line, ['booking' => $b->reference, 'reconfirm' => true], 'client');
        if ($b->client->phone) {
            $this->channels->sms($b->client->phone, "{$this->brand()}: please reconfirm your booking {$b->reference} for {$this->when($b)}: ".$this->url("account/bookings/{$b->reference}"), $b->client);
        }
    }

    public function noOperatorFound(Booking $b): void
    {
        $this->channels->push($b->client, 'No operator available', "We're sorry, no operator was available right now. You have not been charged. Try again shortly or schedule a booking.", ['booking' => $b->reference], 'client');
    }

    public function paymentFailed(Booking $b, float $amount): void
    {
        $line = 'We could not charge '.rands($amount)." for booking {$b->reference}. Please update your card in your account.";
        $this->channels->push($b->client, 'Payment issue', $line, ['booking' => $b->reference], 'client');
        $this->channels->email($b->client, new BrandedMail("Payment issue · {$b->reference}", "Hello {$b->client->firstName()},", [$line], 'Update payment method', $this->url('account/payment')));
    }

    public function verificationResult(User $client, bool $approved, ?string $note = null): void
    {
        $line = $approved
            ? 'Your identity has been verified. You can now book any of our services.'
            : 'We could not verify your identity from the documents provided.'.($note ? " {$note}" : '').' Please upload clear photos again.';
        $this->channels->push($client, $approved ? 'You are verified' : 'Verification needed', $line, [], 'client');
        $this->channels->email($client, new BrandedMail($approved ? 'You are verified' : 'Verification update', "Hello {$client->firstName()},", [$line], 'Open your account', $this->url('account')));
    }

    // ── Operators ────────────────────────────────────────────────────────

    public function offerToOperator(DispatchOffer $offer): void
    {
        $b = $offer->booking;
        $km = $offer->distance_km ? number_format($offer->distance_km, 1).' km away. ' : '';
        $this->channels->push($offer->operator->user, 'New job offer', "{$km}{$b->service->name}, {$b->pickup_address}. Tap to accept.", ['offer' => $offer->id, 'channel' => 'offers'], 'crew');
    }

    public function assignedToOperator(BookingAssignment $a): void
    {
        $b = $a->booking;
        $this->channels->push($a->operator->user, 'New assignment', "{$b->service->name} on {$this->when($b)} as {$a->roleLabel()}. Please accept in the Crew app.", ['job' => $b->id], 'crew');
        if ($a->operator->user->phone) {
            $this->channels->sms($a->operator->user->phone, "{$this->brand()}: new assignment {$b->reference} on {$this->when($b)}. Open the Crew app to accept.", $a->operator->user);
        }
    }

    public function documentExpiring(Document $doc): void
    {
        $subject = $doc->documentable;
        $who = $subject instanceof \App\Models\OperatorProfile ? $subject->user : null;
        $line = "{$doc->type->name} expires on {$doc->expires_on->format('j M Y')}. Please upload the renewed document.";
        if ($who) {
            $this->channels->push($who, 'Document expiring', $line, ['documents' => true], 'crew');
            $this->channels->email($who, new BrandedMail('Document expiring', "Hello {$who->firstName()},", [$line, 'If it lapses, you will be automatically removed from jobs that need it.']));
        }
        $this->staff('Document expiring', ($who?->name ?? ($subject->label() ?? 'Vehicle')).": {$line}", 'documents');
    }

    // ── Staff / control room ─────────────────────────────────────────────

    /** Push to every active staff member with the permission (or all staff). */
    public function staff(string $title, string $body, string $channel = 'general', ?string $permission = 'bookings.view'): void
    {
        $this->staffUsers($permission)->each(fn (User $u) => $this->channels->push($u, $title, $body, ['channel' => $channel], 'client'));
    }

    public function staffNewBooking(Booking $b): void
    {
        $needs = match ($b->status->value) {
            'pending_approval' => 'needs approval',
            'quote_requested' => 'needs a quote',
            default => 'confirmed',
        };
        $this->staff("New booking {$needs}", "{$b->reference} · {$b->service->name} · {$this->when($b)} · {$b->client->name}", 'bookings');
    }

    public function escalateToOnCall(string $title, string $message, int $level): void
    {
        $people = $this->onCall();
        if ($people->isEmpty()) {
            $people = $this->staffUsers('control_room.respond')->take(3);
        }
        foreach ($people as $person) {
            $this->channels->push($person, $title, $message, ['channel' => 'alerts'], 'client');
            if ($level >= 1 && $person->phone) {
                $this->channels->sms($person->phone, "{$this->brand()} ALERT: {$title}. {$message}", $person);
            }
            if ($level >= 2 && $person->phone) {
                $this->channels->voice($person->phone, "{$this->brand()} alert. {$title}. {$message}. Please open the control room now.", $person);
            }
        }
    }

    public function sosRaised(SosAlert $sos): void
    {
        $who = $sos->user->name;
        $where = $sos->lat ? "Location {$sos->lat},{$sos->lng}" : 'Location unknown';
        $this->escalateToOnCall('SOS raised', "{$who} ({$sos->raised_by_role}) raised an SOS. {$where}.".($sos->booking ? " Booking {$sos->booking->reference}." : ''), 0);
        if ($sos->raised_by_role === 'client') {
            foreach ($sos->user->emergencyContacts()->where('notify_on_sos', true)->get() as $contact) {
                $this->channels->sms($contact->phone, "{$this->brand()}: {$sos->user->firstName()} has raised a safety alert. Our control room is responding. Call ".settings('company.control_room_phone').' for updates.', $sos->user);
            }
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return Collection<int, User> */
    public function onCall(): Collection
    {
        return OnCallShift::query()->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->orderBy('priority')->with('user')->get()->pluck('user')->filter()->unique('id')->values();
    }

    /** @return Collection<int, User> */
    private function staffUsers(?string $permission): Collection
    {
        $q = User::query()->where('type', 'staff')->where('status', 'active');
        $users = $q->get();
        if ($permission) {
            $users = $users->filter(function (User $u) use ($permission) {
                try {
                    return $u->hasRole('Owner') || $u->can($permission);
                } catch (\Throwable) {
                    return true;
                }
            });
        }

        return $users->values();
    }

    private function bookingTable(Booking $b): array
    {
        $rows = [
            ['label' => 'Reference', 'value' => $b->reference],
            ['label' => 'Service', 'value' => $b->service->name],
            ['label' => 'When', 'value' => $this->when($b)],
            ['label' => 'Pickup', 'value' => $b->pickup_address],
        ];
        if ($b->dropoff_address) {
            $rows[] = ['label' => 'Drop-off', 'value' => $b->dropoff_address];
        }
        if ($b->principal_name) {
            $rows[] = ['label' => 'Principal', 'value' => $b->principal_name];
        }
        if ($b->quoted_total > 0 && $b->status->value !== 'quote_requested') {
            $rows[] = ['label' => 'Quote', 'value' => rands($b->quoted_total)];
        }

        return $rows;
    }

    private function notifyPrincipal(Booking $b, string $sms): void
    {
        if ($b->principal_phone && $b->principal_phone !== $b->client->phone) {
            $this->channels->sms($b->principal_phone, $sms, $b->client);
        }
    }

    private function principalLink(Booking $b): string
    {
        $share = app(\App\Services\Booking\TripShareService::class)->forPrincipal($b);

        return $this->url('t/'.$share->token);
    }

    public function maskedPhone(?string $phone): string
    {
        return PhoneNumber::mask($phone);
    }
}
