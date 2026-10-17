<?php

namespace App\Services\Booking;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Events\BookingUpdated;
use App\Events\ControlRoomAlert;
use App\Events\OperatorFeedChanged;
use App\Models\Booking;
use App\Models\Company;
use App\Models\OperatorProfile;
use App\Models\PaymentMethod;
use App\Models\Region;
use App\Models\Service;
use App\Models\Strike;
use App\Models\User;
use App\Services\Dispatch\DispatchService;
use App\Services\Maps\MapsService;
use App\Services\Messaging\Notifier;
use App\Services\Payments\PaymentService;
use App\Services\Pricing\PricingService;
use App\Services\Pricing\Quote;
use App\Services\Pricing\QuoteInput;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\Realtime;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private PricingService $pricing,
        private MapsService $maps,
        private PaymentService $payments,
        private Notifier $notifier,
    ) {}

    private function dispatcher(): DispatchService
    {
        return app(DispatchService::class);
    }

    // ── Quoting ──────────────────────────────────────────────────────────

    /**
     * Normalise a booking request and build the pricing input. Throws ValidationException with
     * friendly messages when the request breaks a business rule.
     *
     * @return array{input: QuoteInput, data: array}
     */
    public function prepare(array $data, ?User $client = null): array
    {
        $service = Service::query()->published()->find($data['service_id'] ?? null);
        if (! $service) {
            throw ValidationException::withMessages(['service_id' => 'Please choose a service.']);
        }
        if (! $service->isAvailable()) {
            throw ValidationException::withMessages(['service_id' => 'This service is not available at the moment.']);
        }
        $region = isset($data['region_id']) ? Region::findOrFail($data['region_id']) : Region::default();

        $mode = ($data['mode'] ?? 'advance') === 'on_demand' ? 'on_demand' : 'advance';
        if ($mode === 'on_demand' && (! feature_enabled('on_demand') || ! $service->allows_on_demand)) {
            throw ValidationException::withMessages(['mode' => 'On-demand hailing is not available for this service. Please schedule a booking instead.']);
        }
        if ($mode === 'advance' && (! feature_enabled('advance') || ! $service->allows_advance)) {
            throw ValidationException::withMessages(['mode' => 'This service can only be hailed on demand.']);
        }

        $pickup = $this->place($data['pickup'] ?? null, 'pickup', true);
        if (! $region->contains($pickup['lat'], $pickup['lng'])) {
            throw ValidationException::withMessages(['pickup' => "Pickup is outside our {$region->name} service area. Contact us for a custom quote."]);
        }
        $dropoff = $service->includes_transport ? $this->place($data['dropoff'] ?? null, 'dropoff', false) : null;

        $stops = [];
        if ($service->includes_transport && ! empty($data['stops'])) {
            if (! feature_enabled('multi_stop')) {
                throw ValidationException::withMessages(['stops' => 'Multi-stop trips are not available at the moment.']);
            }
            foreach (array_values($data['stops']) as $i => $s) {
                $p = $this->place($s, "stops.{$i}", true);
                $stops[] = $p + ['wait_minutes' => max(0, min(480, (int) ($s['wait_minutes'] ?? 0)))];
            }
            if (count($stops) > 8) {
                throw ValidationException::withMessages(['stops' => 'A maximum of 8 stops can be added online. Contact us for longer itineraries.']);
            }
        }

        $guards = (int) ($data['guards'] ?? $service->default_guards);
        $vehicles = $service->includes_transport ? max(1, (int) ($data['vehicles'] ?? $service->default_vehicles)) : 0;
        if (! $service->includes_transport) {
            $guards = max(1, $guards);
        }
        $guards = min($guards, 20);
        $vehicles = min($vehicles, 10);

        // Recurring / multi-day schedule
        $shifts = $this->buildShifts($data['schedule'] ?? null, $mode);
        $structure = 'single';
        if ($shifts) {
            $structure = ($data['schedule']['type'] ?? 'multi_day') === 'recurring' ? 'recurring' : 'multi_day';
            if (! feature_enabled($structure === 'recurring' ? 'recurring' : 'multi_day')) {
                throw ValidationException::withMessages(['schedule' => ($structure === 'recurring' ? 'Recurring' : 'Multi-day').' bookings are not available at the moment.']);
            }
        }
        $isTeam = $vehicles > 1 || $guards > max(1, $service->default_guards * max(1, $vehicles));
        if ($isTeam && $structure === 'single') {
            if (! feature_enabled('team_bookings')) {
                throw ValidationException::withMessages(['guards' => 'Team bookings are not available at the moment.']);
            }
            $structure = 'team';
        }
        if ($service->structure === 'team' && $structure === 'single') {
            $structure = 'team';
        }
        if ($mode === 'on_demand' && $structure !== 'single') {
            throw ValidationException::withMessages(['mode' => 'Team and multi-day bookings must be scheduled in advance.']);
        }

        // Timing
        if ($mode === 'on_demand') {
            $startsAt = now()->addMinutes(1)->second(0);
        } else {
            $startsAt = $shifts ? $shifts[0][0] : $this->parseTime($data['starts_at'] ?? null);
            $notice = $structure === 'single' ? max($service->min_notice_minutes, 0) : max((int) settings('booking.notice_minutes_team'), $service->min_notice_minutes);
            $notice = $structure === 'single' ? max($notice, (int) settings('booking.notice_minutes')) : $notice;
            if ($startsAt->lt(now()->addMinutes($notice)->subMinute())) {
                throw ValidationException::withMessages(['starts_at' => 'Please allow at least '.$this->humanMinutes($notice).' notice for this booking, or hail on demand.']);
            }
            if ($startsAt->gt(now()->addDays((int) settings('booking.max_days_ahead')))) {
                throw ValidationException::withMessages(['starts_at' => 'Bookings can be made up to '.settings('booking.max_days_ahead').' days ahead.']);
            }
        }

        // Route
        $points = [[$pickup['lat'], $pickup['lng']]];
        foreach ($stops as $s) {
            $points[] = [$s['lat'], $s['lng']];
        }
        if ($dropoff) {
            $points[] = [$dropoff['lat'], $dropoff['lng']];
        }
        $route = $service->includes_transport ? $this->maps->route($points) : ['km' => 0, 'minutes' => 0];

        $waits = array_sum(array_column($stops, 'wait_minutes'));
        $minimumMinutes = max($service->min_duration_minutes, (int) (ceil(($route['minutes'] + $waits + 15) / 15) * 15));
        $duration = $shifts ? 0 : max($minimumMinutes, (int) ($data['duration_minutes'] ?? 0));
        $duration = min($duration, 24 * 60);

        $featureIds = array_values(array_unique(array_map('intval', array_merge($data['feature_ids'] ?? [], $service->default_feature_ids ?? []))));
        $extras = collect($data['extras'] ?? [])->map(fn ($e) => ['id' => (int) (is_array($e) ? $e['id'] : $e), 'quantity' => max(1, (int) (is_array($e) ? ($e['quantity'] ?? 1) : 1))])
            ->filter(fn ($e) => $service->extras()->published()->whereKey($e['id'])->exists())->values()->all();

        $input = new QuoteInput(
            service: $service,
            region: $region,
            mode: $mode,
            startsAt: $startsAt,
            durationMinutes: $duration ?: 60,
            distanceKm: (float) $route['km'],
            guards: $guards,
            vehicles: max(1, $vehicles),
            extras: $extras,
            featureIds: $featureIds,
            stops: count($stops),
            shifts: $shifts,
        );

        $normalized = [
            'service' => $service, 'region' => $region, 'mode' => $mode, 'structure' => $structure,
            'starts_at' => $startsAt, 'duration_minutes' => $duration ?: (int) $shifts[0][0]->diffInMinutes($shifts[0][1]),
            'ends_at' => $shifts ? end($shifts)[1] : $startsAt->copy()->addMinutes($duration),
            'pickup' => $pickup, 'dropoff' => $dropoff, 'stops' => $stops, 'route' => $route,
            'guards' => $guards, 'vehicles' => $vehicles, 'feature_ids' => $featureIds, 'extras' => $extras,
            'shifts' => $shifts, 'schedule' => $data['schedule'] ?? null,
            'principal_name' => trim((string) ($data['principal']['name'] ?? '')) ?: null,
            'principal_phone' => PhoneNumber::normalize($data['principal']['phone'] ?? null),
            'passengers' => max(1, min(12, (int) ($data['passengers'] ?? 1))),
            'client_notes' => Arr::get($data, 'client_notes'),
            'preferred_operator_id' => feature_enabled('preferred_operator') ? ($data['preferred_operator_id'] ?? null) : null,
            'payment_method' => $data['payment_method'] ?? 'card',
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'minimum_minutes' => $minimumMinutes,
        ];

        return ['input' => $input, 'data' => $normalized];
    }

    public function quote(array $data, ?User $client = null): array
    {
        $prepared = $this->prepare($data, $client);
        $quote = $this->pricing->quote($prepared['input']);

        return [
            'quote' => $quote,
            'route' => $prepared['data']['route'],
            'structure' => $prepared['data']['structure'],
            'duration_minutes' => $prepared['data']['duration_minutes'],
            'minimum_minutes' => $prepared['data']['minimum_minutes'],
            'starts_at' => $prepared['data']['starts_at'],
            'shifts' => count($prepared['data']['shifts']),
        ];
    }

    // ── Creating ─────────────────────────────────────────────────────────

    public function create(array $data, User $client, ?User $actor = null, string $source = 'web'): Booking
    {
        $actor ??= $client;
        $this->assertClientCanBook($client);

        $prepared = $this->prepare($data, $client);
        $d = $prepared['data'];
        $quote = $this->pricing->quote($prepared['input']);
        $service = $d['service'];

        // Company account
        $company = null;
        if ($d['company_id']) {
            $company = $client->companies()->whereKey($d['company_id'])->first();
            if (! $company || ! feature_enabled('company_accounts')) {
                throw ValidationException::withMessages(['company_id' => 'You are not an authorised booker for this company.']);
            }
        }

        // Payment
        [$paymentMethod, $paymentMethodId] = $this->resolvePayment($client, $company, $d['payment_method'], $d['payment_method_id'], $actor);

        // Preferred operator must have worked with this client before
        if ($d['preferred_operator_id']) {
            $worked = Booking::query()->where('client_id', $client->id)->where('status', BookingStatus::Completed->value)
                ->whereHas('assignments', fn ($q) => $q->where('operator_profile_id', $d['preferred_operator_id']))->exists();
            if (! $worked) {
                $d['preferred_operator_id'] = null;
            }
        }

        $status = $this->initialStatus($client, $service, $d, $quote);

        $booking = DB::transaction(function () use ($client, $actor, $company, $d, $quote, $service, $status, $paymentMethod, $paymentMethodId, $source) {
            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'client_id' => $client->id,
                'company_id' => $company?->id,
                'booked_by_id' => $actor->id,
                'service_id' => $service->id,
                'region_id' => $d['region']->id,
                'mode' => $d['mode'],
                'structure' => $d['structure'],
                'status' => $status,
                'source' => $source,
                'principal_name' => $d['principal_name'],
                'principal_phone' => $d['principal_phone'],
                'passengers' => $d['passengers'],
                'starts_at' => $d['starts_at'],
                'ends_at' => $d['ends_at'],
                'duration_minutes' => $d['duration_minutes'],
                'recurrence' => $d['schedule'],
                'pickup_address' => $d['pickup']['address'],
                'pickup_lat' => $d['pickup']['lat'],
                'pickup_lng' => $d['pickup']['lng'],
                'dropoff_address' => $d['dropoff']['address'] ?? null,
                'dropoff_lat' => $d['dropoff']['lat'] ?? null,
                'dropoff_lng' => $d['dropoff']['lng'] ?? null,
                'estimated_km' => $d['route']['km'],
                'guards_count' => $d['guards'],
                'vehicles_count' => $d['vehicles'],
                'feature_ids' => $d['feature_ids'],
                'preferred_operator_id' => $d['preferred_operator_id'],
                'client_notes' => $d['client_notes'],
                'quote_breakdown' => $quote->toArray(),
                'quoted_total' => $quote->customQuote ? 0 : $quote->total,
                'overtime_rate' => $quote->overtimeRate,
                'payment_method' => $paymentMethod,
                'payment_method_id' => $paymentMethodId,
                'payment_status' => 'unpaid',
                'arrival_pin' => Booking::generatePin(),
                'reconfirm_due_at' => $this->reconfirmDue($d['mode'], $d['starts_at']),
            ]);

            foreach ($d['stops'] as $i => $s) {
                $booking->stops()->create(['sequence' => $i + 1, 'address' => $s['address'], 'lat' => $s['lat'], 'lng' => $s['lng'], 'wait_minutes' => $s['wait_minutes']]);
            }
            foreach ($d['shifts'] as [$start, $end]) {
                $booking->shifts()->create(['starts_at' => $start, 'ends_at' => $end]);
            }
            foreach ($d['extras'] as $e) {
                $line = collect($quote->lines)->first(fn ($l) => str_starts_with($l['label'], \App\Models\Extra::find($e['id'])?->name ?? '§'));
                $booking->extras()->create(['extra_id' => $e['id'], 'quantity' => $e['quantity'], 'amount' => $line['amount'] ?? 0]);
            }

            $booking->logEvent('booking.created', 'Booking requested ('.$booking->status->getLabel().')', ['source' => $source], $actor->id);

            return $booking;
        });

        $booking->refresh();
        $this->notifier->bookingReceived($booking);

        if (in_array($booking->status, [BookingStatus::PendingApproval, BookingStatus::QuoteRequested], true)) {
            $this->notifier->staffNewBooking($booking);
            Realtime::send(new ControlRoomAlert('booking', 'New booking '.$booking->reference, $booking->status->getLabel(), ['booking_id' => $booking->id]));
        } else {
            $this->afterConfirmed($booking);
        }
        Realtime::send(new BookingUpdated($booking, 'created'));

        return $booking;
    }

    private function initialStatus(User $client, Service $service, array $d, Quote $quote): BookingStatus
    {
        if ($quote->customQuote) {
            return BookingStatus::QuoteRequested;
        }
        $profile = $client->clientProfile;
        $completed = $client->bookings()->where('status', BookingStatus::Completed->value)->count();
        $strikes = feature_enabled('strikes') ? $client->activeStrikesCount() : 0;

        $needsApproval = $service->approval_rule === 'always'
            || ($service->approval_rule === 'first_booking' && $completed === 0)
            || $d['structure'] !== 'single'
            || ($profile?->requires_approval ?? false)
            || ($strikes >= (int) settings('strikes.approval_threshold'))
            || (feature_enabled('id_verification') && ! ($profile?->isVerified() ?? false))
            || ! ($profile?->isTrusted() ?? false);

        if (! $needsApproval && $d['mode'] === 'advance' && ! $this->hasCapacity($service, $d)) {
            $needsApproval = true;
        }

        if ($needsApproval) {
            return BookingStatus::PendingApproval;
        }

        return $d['mode'] === 'on_demand' ? BookingStatus::Searching : BookingStatus::Confirmed;
    }

    /** Rough check that enough compliant operators are free at that time. */
    private function hasCapacity(Service $service, array $d): bool
    {
        $needed = ($service->includes_transport ? max(1, $d['vehicles']) : 0) + ($d['guards'] ?? 0);
        $start = $d['starts_at'];
        $end = $d['ends_at'];
        $busy = \App\Models\BookingAssignment::query()
            ->whereIn('status', array_map(fn ($s) => $s->value, AssignmentStatus::live()))
            ->whereHas('booking', fn ($q) => $q->where('starts_at', '<', $end)->where('ends_at', '>', $start))
            ->distinct()->pluck('operator_profile_id');
        $free = OperatorProfile::query()->active()->whereNotIn('id', $busy)->count();

        return $free >= $needed;
    }

    public function assertClientCanBook(User $client): void
    {
        if (! $client->isClient() && ! $client->isStaff()) {
            throw ValidationException::withMessages(['account' => 'Only client accounts can make bookings.']);
        }
        if ($client->status !== 'active') {
            throw ValidationException::withMessages(['account' => 'Your account is suspended. Please contact us.']);
        }
        if (feature_enabled('strikes') && $client->activeStrikesCount() >= (int) settings('strikes.block_threshold')) {
            throw ValidationException::withMessages(['account' => 'Your account has been suspended after repeated missed bookings. Please contact us.']);
        }
        if (feature_enabled('id_verification') && $client->isClient()) {
            $status = $client->clientProfile?->verification_status ?? 'unverified';
            if (in_array($status, ['unverified', 'rejected'], true)) {
                throw ValidationException::withMessages(['verification' => 'Before your first booking, please verify your identity with a photo of your ID or passport and a selfie.']);
            }
        }
    }

    private function resolvePayment(User $client, ?Company $company, string $method, $methodId, ?User $actor = null): array
    {
        $staffOverride = $actor?->isStaff() && $actor->id !== $client->id;
        if ($method === 'eft') {
            $allowed = feature_enabled('eft_invoicing') && (($company?->eft_approved ?? false) || ($client->clientProfile?->eft_approved ?? false) || $staffOverride);
            if (! $allowed) {
                throw ValidationException::withMessages(['payment_method' => 'EFT invoicing is only available to approved accounts.']);
            }

            return ['eft', null];
        }
        if ($method === 'card_machine' && ! feature_enabled('card_machine')) {
            $method = 'card';
        }
        $pm = $methodId ? $client->paymentMethods()->find($methodId) : $client->defaultPaymentMethod();
        if (! $pm && $staffOverride && in_array($method, ['card_machine', 'eft'], true)) {
            return [$method === 'eft' ? 'eft' : 'card_machine', null]; // phone booking, staff take responsibility
        }
        if (! $pm) {
            throw ValidationException::withMessages(['payment_method_id' => 'Please add a card. Nothing is charged now: we only charge after the job.']);
        }
        if ($pm->isExpired()) {
            throw ValidationException::withMessages(['payment_method_id' => 'That card has expired. Please add a new one.']);
        }

        return [in_array($method, ['card', 'card_machine'], true) ? $method : 'card', $pm->id];
    }

    // ── Staff decisions ──────────────────────────────────────────────────

    public function approve(Booking $booking, User $staff): Booking
    {
        if ($booking->status !== BookingStatus::PendingApproval) {
            throw ValidationException::withMessages(['status' => 'Only bookings awaiting approval can be approved.']);
        }
        $booking->update([
            'status' => $booking->isOnDemand() ? BookingStatus::Searching : BookingStatus::Confirmed,
            'approved_at' => now(), 'approved_by' => $staff->id,
        ]);
        $booking->logEvent('booking.approved', 'Approved by '.$staff->name, [], $staff->id);
        $this->notifier->bookingConfirmed($booking);
        $this->afterConfirmed($booking);
        Realtime::send(new BookingUpdated($booking, 'approved'));

        return $booking;
    }

    public function decline(Booking $booking, User $staff, ?string $reason = null): Booking
    {
        if ($booking->status->isClosed()) {
            throw ValidationException::withMessages(['status' => 'This booking is already closed.']);
        }
        $this->payments->releaseHold($booking);
        $this->dispatcher()->cancelAllOffers($booking);
        $booking->update(['status' => BookingStatus::Declined, 'cancellation_reason' => $reason, 'cancelled_at' => now(), 'cancelled_by' => $staff->id]);
        $booking->logEvent('booking.declined', 'Declined'.($reason ? ": {$reason}" : ''), [], $staff->id);
        $this->notifier->bookingDeclined($booking, $reason);
        Realtime::send(new BookingUpdated($booking, 'declined'));

        return $booking;
    }

    public function sendQuote(Booking $booking, float $total, ?string $note, User $staff): Booking
    {
        if (! in_array($booking->status, [BookingStatus::QuoteRequested, BookingStatus::Quoted, BookingStatus::PendingApproval], true)) {
            throw ValidationException::withMessages(['status' => 'A quote can only be sent before the booking is confirmed.']);
        }
        $total = Money::roundQuote($total);
        $breakdown = $booking->quote_breakdown ?? [];
        $breakdown['custom_total'] = $total;
        $breakdown['custom_note'] = $note;
        $breakdown['total'] = $total;
        $breakdown['no_show_fee'] = $this->pricing->noShowFee($total);
        $breakdown['hold_amount'] = feature_enabled('card_holds') ? $breakdown['no_show_fee'] : 0;
        $booking->update(['status' => BookingStatus::Quoted, 'quoted_total' => $total, 'quote_breakdown' => $breakdown, 'quote_sent_at' => now()]);
        $booking->logEvent('booking.quoted', 'Quote sent: '.rands($total), ['note' => $note], $staff->id);
        $this->notifier->quoteReady($booking);
        Realtime::send(new BookingUpdated($booking, 'quoted'));

        return $booking;
    }

    public function acceptQuote(Booking $booking, User $client): Booking
    {
        if ($booking->status !== BookingStatus::Quoted) {
            throw ValidationException::withMessages(['status' => 'There is no quote waiting for acceptance.']);
        }
        $booking->update(['status' => BookingStatus::Confirmed, 'quote_accepted_at' => now(), 'approved_at' => now()]);
        $booking->logEvent('booking.quote_accepted', 'Quote accepted by client', [], $client->id);
        $this->notifier->bookingConfirmed($booking);
        $this->afterConfirmed($booking);
        Realtime::send(new BookingUpdated($booking, 'confirmed'));

        return $booking;
    }

    private function afterConfirmed(Booking $booking): void
    {
        $this->payments->placeHold($booking);
        if ($booking->status === BookingStatus::Searching) {
            $this->dispatcher()->startOnDemand($booking);
        } elseif ($booking->status === BookingStatus::Confirmed) {
            $this->dispatcher()->autoAssignPreferred($booking);
        }
    }

    // ── Client actions ───────────────────────────────────────────────────

    public function reconfirm(Booking $booking, User $user): Booking
    {
        if ($booking->status->isClosed() || $booking->reconfirmed_at) {
            return $booking;
        }
        $booking->update(['reconfirmed_at' => now()]);
        $booking->logEvent('booking.reconfirmed', 'Client reconfirmed', [], $user->id);
        Realtime::send(new BookingUpdated($booking, 'reconfirmed'));

        return $booking;
    }

    /** @return array{fee: float, reason: string} */
    public function cancellationFee(Booking $booking, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        if (! feature_enabled('no_show_fee')) {
            return ['fee' => 0.0, 'reason' => 'No cancellation fees apply.'];
        }
        if (in_array($booking->status, [BookingStatus::PendingApproval, BookingStatus::QuoteRequested, BookingStatus::Quoted], true)) {
            return ['fee' => 0.0, 'reason' => 'Free: the booking was not yet confirmed.'];
        }
        $arrived = $booking->activeAssignments()->whereIn('status', [AssignmentStatus::Arrived->value, AssignmentStatus::OnJob->value])->exists();
        if ($arrived) {
            return ['fee' => $this->pricing->noShowFee($booking->quoted_total), 'reason' => 'Your operator has already arrived, so the no-show fee applies.'];
        }

        if ($booking->isOnDemand()) {
            $accepted = $booking->activeAssignments()->exists();
            $freeMinutes = (int) settings('cancellation.on_demand_free_minutes');
            if (! $accepted || $booking->created_at->diffInMinutes($at) < $freeMinutes) {
                return ['fee' => 0.0, 'reason' => 'Free: no operator has accepted yet, or you are within the free cancellation window.'];
            }
            $callout = (float) collect($booking->quote_breakdown['lines'] ?? [])->first(fn ($l) => str_starts_with($l['label'], 'Call-out fee'))['amount'] ?? 0;

            return ['fee' => Money::round($callout), 'reason' => 'An operator has accepted and is on the way, so the call-out fee applies.'];
        }

        $hoursBefore = $at->diffInMinutes($booking->starts_at, false) / 60;
        $tiers = collect(settings('cancellation.tiers', []))->sortByDesc('hours')->values();
        $percent = 0;
        foreach ($tiers as $tier) {
            if ($hoursBefore >= (float) $tier['hours']) {
                $percent = (float) $tier['percent'];
                break;
            }
            $percent = (float) $tier['percent'];
        }
        $base = $booking->quoted_total;
        if (in_array($booking->structure, ['multi_day', 'recurring'], true) && $booking->shifts()->count() > 1) {
            $freeHours = (float) ($tiers->first()['hours'] ?? 24);
            $total = $booking->shifts()->count();
            $within = $booking->shifts()->where('starts_at', '<', $at->copy()->addHours($freeHours))->count();
            $base = $booking->quoted_total * ($within / max(1, $total));
        }
        $fee = Money::round($base * $percent / 100);

        return ['fee' => $fee, 'reason' => $fee > 0
            ? "Cancelling ".$this->humanMinutes((int) max(0, $hoursBefore * 60))." before the start: {$percent}% of the quote applies."
            : 'Free: you are cancelling with enough notice.'];
    }

    public function cancel(Booking $booking, User $actor, ?string $reason = null, bool $waiveFee = false): float
    {
        if (! $booking->status->isCancellable()) {
            throw ValidationException::withMessages(['status' => 'This booking can no longer be cancelled. Please contact our control room.']);
        }
        $byStaff = $actor->isStaff();
        $fee = $waiveFee ? 0.0 : $this->cancellationFee($booking)['fee'];

        $this->dispatcher()->cancelAllOffers($booking);
        $booking->update([
            'status' => BookingStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $actor->id,
            'cancellation_reason' => $reason, 'cancellation_fee' => $fee,
        ]);
        $assignments = $booking->activeAssignments()->get();
        foreach ($assignments as $a) {
            $a->update(['status' => AssignmentStatus::Removed]);
            Realtime::send(new OperatorFeedChanged($a->operator_profile_id, 'cancelled', ['booking_id' => $booking->id]));
        }
        $booking->logEvent('booking.cancelled', 'Cancelled by '.($byStaff ? 'staff' : 'client').($fee > 0 ? ' · fee '.rands($fee) : ''), ['reason' => $reason], $actor->id);

        if ($fee > 0) {
            $this->payments->collect($booking, $fee, 'fee', 'Cancellation fee');
        } else {
            $this->payments->releaseHold($booking);
            $booking->update(['payment_status' => $booking->payment_status === 'held' ? 'unpaid' : $booking->payment_status]);
        }

        $this->notifier->bookingCancelled($booking, $fee, $byStaff && $waiveFee);
        Realtime::send(new BookingUpdated($booking, 'cancelled'));

        return $fee;
    }

    public function recordNoShow(Booking $booking, ?User $actor = null): float
    {
        $fee = $this->pricing->noShowFee($booking->quoted_total);
        $this->dispatcher()->cancelAllOffers($booking);
        $booking->update(['status' => BookingStatus::NoShow, 'no_show_at' => now(), 'cancellation_fee' => $fee]);
        foreach ($booking->activeAssignments()->get() as $a) {
            $a->update(['status' => AssignmentStatus::Done, 'finished_at' => now()]);
        }
        $booking->logEvent('booking.no_show', 'Recorded as no-show'.($fee > 0 ? ' · fee '.rands($fee) : ''), [], $actor?->id);
        if ($fee > 0) {
            $this->payments->collect($booking, $fee, 'fee', 'No-show fee');
        }
        $this->addStrike($booking->client, $booking, 'No-show');
        $this->notifier->noShowRecorded($booking, $fee);
        Realtime::send(new BookingUpdated($booking, 'no_show'));

        return $fee;
    }

    public function addStrike(User $client, ?Booking $booking, string $reason): void
    {
        if (! feature_enabled('strikes')) {
            return;
        }
        Strike::create(['user_id' => $client->id, 'booking_id' => $booking?->id, 'reason' => $reason]);
        $count = $client->activeStrikesCount();
        if ($count >= (int) settings('strikes.approval_threshold')) {
            $client->clientProfile?->update(['requires_approval' => true]);
        }
        if ($count >= (int) settings('strikes.block_threshold')) {
            $client->update(['status' => 'suspended']);
            $this->notifier->staff('Client suspended', "{$client->name} reached {$count} strikes and was suspended.", 'clients', 'clients.update');
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function reconfirmDue(string $mode, CarbonInterface $startsAt): ?CarbonInterface
    {
        if ($mode !== 'advance' || ! feature_enabled('reconfirmation')) {
            return null;
        }
        $hours = (int) settings('booking.reconfirm_hours');
        if ($startsAt->lt(now()->addHours($hours + 2))) {
            return null; // booked at short notice: the booking itself is the confirmation
        }

        return $startsAt->copy()->subHours($hours);
    }

    /** @return array{address: string, lat: float, lng: float}|null */
    private function place(mixed $p, string $field, bool $required): ?array
    {
        if (! is_array($p) || ! isset($p['lat'], $p['lng']) || ! is_numeric($p['lat']) || ! is_numeric($p['lng'])) {
            if ($required) {
                throw ValidationException::withMessages([$field => 'Please choose a location from the suggestions or the map.']);
            }

            return null;
        }
        $lat = (float) $p['lat'];
        $lng = (float) $p['lng'];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw ValidationException::withMessages([$field => 'That location looks invalid.']);
        }
        $address = trim((string) ($p['address'] ?? '')) ?: ($this->maps->reverse($lat, $lng) ?? sprintf('%.5f, %.5f', $lat, $lng));

        return ['address' => mb_substr($address, 0, 250), 'lat' => $lat, 'lng' => $lng];
    }

    private function parseTime(mixed $value): Carbon
    {
        try {
            return Carbon::parse($value)->timezone(config('app.timezone'))->second(0);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['starts_at' => 'Please choose a date and time.']);
        }
    }

    /**
     * schedule: {type: multi_day, start_date, end_date, start_time, end_time}
     *        or {type: recurring, weekdays: [1..7], start_date, until, start_time, end_time}
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function buildShifts(?array $schedule, string $mode): array
    {
        if (! $schedule || empty($schedule['type']) || $mode !== 'advance') {
            return [];
        }
        $tz = config('app.timezone');
        try {
            $from = Carbon::parse($schedule['start_date'], $tz)->startOfDay();
            $until = Carbon::parse($schedule['type'] === 'recurring' ? ($schedule['until'] ?? $schedule['end_date']) : $schedule['end_date'], $tz)->startOfDay();
            [$sh, $sm] = array_map('intval', explode(':', $schedule['start_time']));
            [$eh, $em] = array_map('intval', explode(':', $schedule['end_time']));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['schedule' => 'Please complete the schedule dates and times.']);
        }
        if ($until->lt($from)) {
            throw ValidationException::withMessages(['schedule' => 'The end date must be after the start date.']);
        }
        if ($from->diffInDays($until) > 92) {
            throw ValidationException::withMessages(['schedule' => 'Online bookings can cover up to three months. Contact us for longer details.']);
        }
        $weekdays = $schedule['type'] === 'recurring' ? array_map('intval', $schedule['weekdays'] ?? []) : [1, 2, 3, 4, 5, 6, 7];
        if (! $weekdays) {
            throw ValidationException::withMessages(['schedule' => 'Choose at least one day of the week.']);
        }
        $shifts = [];
        for ($day = $from->copy(); $day->lte($until); $day->addDay()) {
            if (! in_array($day->dayOfWeekIso, $weekdays, true)) {
                continue;
            }
            $start = $day->copy()->setTime($sh, $sm);
            $end = $day->copy()->setTime($eh, $em);
            if ($end->lte($start)) {
                $end->addDay(); // overnight shift
            }
            $shifts[] = [$start, $end];
        }
        if (! $shifts) {
            throw ValidationException::withMessages(['schedule' => 'No days fall within that schedule.']);
        }
        if (count($shifts) > 120) {
            throw ValidationException::withMessages(['schedule' => 'That schedule has too many shifts to book online. Please contact us.']);
        }

        return $shifts;
    }

    public function humanMinutes(int $minutes): string
    {
        if ($minutes >= 1440 && $minutes % 1440 === 0) {
            $d = $minutes / 1440;

            return $d.' '.($d === 1 ? 'day' : 'days');
        }
        if ($minutes >= 60) {
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;

            return $h.' '.($h === 1 ? 'hour' : 'hours').($m ? " {$m} min" : '');
        }

        return $minutes.' min';
    }
}
