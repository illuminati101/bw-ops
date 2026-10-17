<?php

namespace App\Http\Controllers\Api\V1\Crew;

use App\Enums\AssignmentStatus;
use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\BookingAssignment;
use App\Models\DispatchOffer;
use App\Models\DocumentType;
use App\Models\OperatorProfile;
use App\Models\Rating;
use App\Services\Booking\JobService;
use App\Services\Compliance\ComplianceService;
use App\Services\Dispatch\DispatchService;
use App\Services\Finance\PayoutService;
use App\Services\Messaging\Channels;
use App\Support\Realtime;
use Illuminate\Http\Request;

class CrewController extends Controller
{
    private function op(Request $request): OperatorProfile
    {
        $op = $request->user()->operatorProfile;
        abort_unless($op && $request->user()->isOperator(), 403, 'Crew access only.');

        return $op;
    }

    private function assignment(Request $request, int $id): BookingAssignment
    {
        return BookingAssignment::query()->where('operator_profile_id', $this->op($request)->id)
            ->with(['booking.service', 'booking.stops', 'vehicle', 'operator', 'shift'])->findOrFail($id);
    }

    public function me(Request $request, ComplianceService $compliance, PayoutService $payouts)
    {
        $op = $this->op($request)->load(['user', 'currentVehicle', 'features', 'documents.type']);
        $effective = $compliance->effectiveFeatureIds($op);

        return [
            'operator' => [
                'id' => $op->id,
                'name' => $op->user->name,
                'first_name' => $op->displayName(),
                'call_sign' => $op->call_sign,
                'phone' => $op->user->phone,
                'photo_url' => $op->user->avatar_path ? asset('storage/'.$op->user->avatar_path) : null,
                'employment_type' => $op->employment_type,
                'roles' => explode(',', $op->roles),
                'status' => $op->status,
                'is_online' => $op->is_online,
                'psira_number' => $op->psira_number,
                'psira_grade' => $op->psira_grade,
                'rating' => $op->rating_avg,
                'vehicle' => $op->currentVehicle ? ['id' => $op->currentVehicle->id, 'label' => $op->currentVehicle->label(), 'class' => $op->currentVehicle->classLabel()] : null,
                'features' => $op->features->map(fn ($f) => ['name' => $f->name, 'active' => in_array($f->id, $effective, true)]),
                'can_work' => $compliance->canWork($op),
                'issues' => $compliance->issues($op),
                'safety_pin_set' => (bool) $request->user()->safety_pin_hash,
            ],
            'earnings' => $op->isContractor() ? $payouts->earningsSummary($op) : null,
            'control_room_phone' => settings('company.control_room_phone'),
        ];
    }

    public function status(Request $request, JobService $jobs)
    {
        $data = $request->validate(['online' => 'required|boolean', 'lat' => 'nullable|numeric', 'lng' => 'nullable|numeric']);
        $op = $jobs->goOnline($this->op($request), $data['online'], $data['lat'] ?? null, $data['lng'] ?? null);

        return ['is_online' => $op->is_online];
    }

    public function location(Request $request, JobService $jobs)
    {
        $data = $request->validate([
            'points' => 'required|array|min:1|max:100',
            'points.*.lat' => 'required|numeric|between:-90,90',
            'points.*.lng' => 'required|numeric|between:-180,180',
            'points.*.heading' => 'nullable|numeric', 'points.*.speed' => 'nullable|numeric', 'points.*.accuracy' => 'nullable|numeric', 'points.*.at' => 'nullable|date',
        ]);
        $jobs->recordLocation($this->op($request), $data['points']);

        return response()->noContent();
    }

    /** Everything the Crew home screen needs in one call (also used for polling when websockets are unavailable). */
    public function feed(Request $request)
    {
        $op = $this->op($request);
        $offers = DispatchOffer::query()->where('operator_profile_id', $op->id)->where('status', 'pending')->where('expires_at', '>', now())
            ->with('booking.service')->get()->map(fn ($o) => [
                'id' => $o->id,
                'role' => $o->role,
                'service' => $o->booking->service->name,
                'pickup' => $o->booking->pickup_address,
                'pickup_lat' => $o->booking->pickup_lat,
                'pickup_lng' => $o->booking->pickup_lng,
                'dropoff' => $o->booking->dropoff_address,
                'distance_km' => $o->distance_km,
                'duration_minutes' => $o->booking->duration_minutes,
                'requirements' => \App\Models\Feature::query()->whereIn('id', $o->booking->feature_ids ?? [])->pluck('name'),
                'expires_at' => $o->expires_at->toIso8601String(),
                'seconds_left' => max(0, now()->diffInSeconds($o->expires_at, false)),
                'payout_estimate' => $op->isContractor() ? round(($o->booking->duration_minutes / 60) * (float) $op->pay_rate_hourly, 2) : null,
            ]);

        $jobs = $op->assignments()->whereIn('status', array_map(fn ($s) => $s->value, AssignmentStatus::live()))
            ->whereHas('booking', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled', 'no_show', 'declined', 'expired']))
            ->with(['booking.service', 'booking.stops', 'vehicle', 'operator', 'shift'])->get()
            ->sortBy(fn ($a) => ($a->shift?->starts_at ?? $a->booking->starts_at)->timestamp)->values();

        $current = $jobs->first(fn ($a) => in_array($a->status, [AssignmentStatus::EnRoute, AssignmentStatus::Arrived, AssignmentStatus::OnJob], true))
            ?? $jobs->first(fn ($a) => $a->booking->isOnDemand() && $a->status === AssignmentStatus::Accepted);

        return [
            'is_online' => $op->is_online,
            'offers' => $offers,
            'current' => $current ? new JobResource($current) : null,
            'awaiting_response' => JobResource::collection($jobs->where('status', AssignmentStatus::Assigned)->values()),
            'upcoming' => JobResource::collection($jobs->where('status', AssignmentStatus::Accepted)->reject(fn ($a) => $current && $a->id === $current->id)->values()),
            'server_time' => now()->toIso8601String(),
        ];
    }

    public function acceptOffer(Request $request, int $id, DispatchService $dispatch)
    {
        $offer = DispatchOffer::query()->findOrFail($id);
        $assignment = $dispatch->accept($offer, $this->op($request));

        return ['job' => new JobResource($assignment->load(['booking.service', 'booking.stops', 'vehicle', 'operator']))];
    }

    public function declineOffer(Request $request, int $id, DispatchService $dispatch)
    {
        $dispatch->decline(DispatchOffer::query()->findOrFail($id), $this->op($request));

        return response()->noContent();
    }

    public function respond(Request $request, int $id, DispatchService $dispatch)
    {
        $data = $request->validate(['accept' => 'required|boolean']);
        $a = $this->assignment($request, $id);
        $dispatch->respondToAssignment($a, $this->op($request), $data['accept']);

        return ['job' => new JobResource($a->fresh(['booking.service', 'booking.stops', 'vehicle', 'operator']))];
    }

    public function job(Request $request, int $id)
    {
        return ['job' => new JobResource($this->assignment($request, $id))];
    }

    public function action(Request $request, int $id, JobService $jobs)
    {
        $data = $request->validate([
            'action' => 'required|in:en_route,arrived,start,complete,no_show,card_machine_paid',
            'pin' => 'nullable|string|max:8',
            'pin_override_reason' => 'nullable|string|max:300',
            'reference' => 'nullable|string|max:64',
            'actual_km' => 'nullable|numeric|min:0|max:2000',
        ]);
        $a = $jobs->update($this->assignment($request, $id), $this->op($request), $data['action'], $data);

        return ['job' => new JobResource($a->load(['booking.service', 'booking.stops', 'vehicle', 'operator']))];
    }

    public function verifyPin(Request $request, int $id, JobService $jobs)
    {
        $data = $request->validate(['pin' => 'required|string|max:8']);
        $a = $this->assignment($request, $id);

        return ['valid' => $jobs->verifyPin($a->booking, $data['pin'])];
    }

    public function messages(Request $request, int $id)
    {
        $b = $this->assignment($request, $id)->booking;
        $b->messages()->where('sender_role', '!=', 'operator')->whereNull('read_at')->update(['read_at' => now()]);

        return ['messages' => $b->messages()->get()->map(fn ($m) => [
            'id' => $m->id, 'mine' => $m->sender_id === $request->user()->id, 'sender_role' => $m->sender_role, 'body' => $m->body, 'created_at' => $m->created_at->toIso8601String(),
        ]), 'open' => $b->chatOpen()];
    }

    public function sendMessage(Request $request, int $id)
    {
        $data = $request->validate(['body' => 'required|string|max:1000']);
        $b = $this->assignment($request, $id)->booking;
        abort_unless($b->chatOpen(), 422, 'Chat is closed for this job.');
        $m = $b->messages()->create(['sender_id' => $request->user()->id, 'sender_role' => 'operator', 'body' => $data['body']]);
        Realtime::send(new ChatMessageSent($m));
        app(Channels::class)->push($b->client, 'Message from your operator', mb_strimwidth($data['body'], 0, 90, '…'), ['booking' => $b->reference, 'chat' => true], 'client');

        return ['message' => ['id' => $m->id, 'mine' => true, 'sender_role' => 'operator', 'body' => $m->body, 'created_at' => $m->created_at->toIso8601String()]];
    }

    public function call(Request $request, int $id, Channels $channels)
    {
        $b = $this->assignment($request, $id)->booking;
        $to = $b->principal_phone ?: $b->client->phone;
        abort_unless(feature_enabled('masked_calls') && $channels->twilioReady() && $to && $request->user()->phone, 422, 'Calling is not available. Use chat or call the control room.');
        $channels->bridgeCall($request->user()->phone, $to, $request->user());
        $b->logEvent('call.operator_to_client', 'Masked call started by operator', [], $request->user()->id);

        return ['message' => 'Calling you now. You will be connected to the client.'];
    }

    public function rate(Request $request, int $id)
    {
        $data = $request->validate(['score' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:1000', 'flag' => 'boolean']);
        $a = $this->assignment($request, $id);
        Rating::updateOrCreate(
            ['booking_id' => $a->booking_id, 'rater_id' => $request->user()->id, 'direction' => 'operator_to_client'],
            ['ratee_id' => $a->booking->client_id, 'score' => $data['score'], 'comment' => $data['comment'] ?? null, 'flagged' => ($data['flag'] ?? false) || $data['score'] <= 2]
        );
        if (($data['flag'] ?? false) || $data['score'] <= 2) {
            app(\App\Services\Messaging\Notifier::class)->staff('Client flagged by operator', "{$a->booking->reference}: {$a->operator->label()} flagged the client.", 'quality', 'clients.view');
        }

        return response()->noContent();
    }

    public function history(Request $request)
    {
        $op = $this->op($request);
        $items = $op->assignments()->where('status', AssignmentStatus::Done->value)->with(['booking.service', 'operator', 'payout'])
            ->latest('finished_at')->paginate(30);

        return [
            'data' => $items->getCollection()->map(fn ($a) => [
                'id' => $a->id, 'reference' => $a->booking->reference, 'service' => $a->booking->service->name, 'role' => $a->roleLabel(),
                'finished_at' => $a->finished_at?->toIso8601String(), 'payout_amount' => $op->isContractor() ? $a->payout_amount : null,
                'payout_status' => $a->payout?->status ?? ($op->isContractor() ? 'unpaid' : null),
            ]),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage()],
        ];
    }

    public function documents(Request $request)
    {
        $op = $this->op($request)->load('documents.type');
        $types = DocumentType::query()->where('applies_to', 'operator')->orderBy('name')->get();

        return [
            'types' => $types->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'requires_expiry' => $t->requires_expiry]),
            'documents' => $op->documents->sortByDesc('id')->values()->map(fn ($d) => [
                'id' => $d->id, 'type' => $d->type->name, 'number' => $d->number, 'expires_on' => $d->expires_on?->toDateString(),
                'status' => $d->status, 'valid' => $d->isValid(), 'expiring_soon' => $d->expires_on && $d->expires_on->lte(today()->addDays(30)),
            ]),
        ];
    }

    public function uploadDocument(Request $request)
    {
        $data = $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'file' => 'required|file|mimes:jpg,jpeg,png,heic,pdf|max:10240',
            'number' => 'nullable|string|max:64',
            'expires_on' => 'nullable|date|after:today',
        ]);
        $op = $this->op($request);
        $doc = $op->documents()->create([
            'document_type_id' => $data['document_type_id'],
            'file_path' => $request->file('file')->store('documents/operators/'.$op->id, 'local'),
            'number' => $data['number'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'status' => 'pending',
        ]);
        app(\App\Services\Messaging\Notifier::class)->staff('Document uploaded', "{$op->label()} uploaded {$doc->type->name} for review.", 'documents', 'documents.update');

        return response()->json(['id' => $doc->id, 'status' => 'pending'], 201);
    }

    public function roster(Request $request)
    {
        $op = $this->op($request);

        return ['shifts' => $op->rosterShifts()->where('ends_at', '>=', now()->subDay())->orderBy('starts_at')->get()->map(fn ($s) => [
            'id' => $s->id, 'starts_at' => $s->starts_at->toIso8601String(), 'ends_at' => $s->ends_at->toIso8601String(), 'status' => $s->status, 'notes' => $s->notes,
        ])];
    }

    public function requestShift(Request $request)
    {
        $data = $request->validate(['starts_at' => 'required|date|after:now', 'ends_at' => 'required|date|after:starts_at', 'notes' => 'nullable|string|max:200']);
        $shift = $this->op($request)->rosterShifts()->create($data + ['status' => 'requested']);

        return response()->json(['id' => $shift->id, 'status' => 'requested'], 201);
    }
}
