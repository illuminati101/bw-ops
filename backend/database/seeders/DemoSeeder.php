<?php

namespace Database\Seeders;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Company;
use App\Models\DocumentType;
use App\Models\Feature;
use App\Models\OnCallShift;
use App\Models\OperatorProfile;
use App\Models\Region;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * Sample operators, vehicles and clients so the apps can be tested end to end.
 * Everything here is fictional. Delete it from the admin panel before going live.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('email', 'dispatch@blackandwhiteops.com')->exists()) {
            return;
        }
        $region = Region::query()->firstOrFail();
        $password = env('ADMIN_PASSWORD', 'ChangeMe-2026!');

        $dispatcher = User::create(['name' => 'Demo Dispatcher', 'email' => 'dispatch@blackandwhiteops.com', 'phone' => '+27820000099', 'type' => 'staff', 'status' => 'active', 'password' => $password, 'email_verified_at' => now()]);
        $dispatcher->syncRoles(['Dispatcher']);
        $owner = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'Owner'))->first();
        OnCallShift::create(['user_id' => ($owner ?? $dispatcher)->id, 'starts_at' => now()->startOfDay(), 'ends_at' => now()->addDays(60), 'priority' => 1]);
        OnCallShift::create(['user_id' => $dispatcher->id, 'starts_at' => now()->startOfDay(), 'ends_at' => now()->addDays(60), 'priority' => 2]);

        $f = fn (string $slug) => Feature::query()->where('slug', $slug)->value('id');
        $dt = fn (string $slug) => DocumentType::query()->where('slug', $slug)->firstOrFail();

        $vehicles = [
            'sedan' => Vehicle::create(['make' => 'Mercedes-Benz', 'model' => 'E-Class', 'year' => 2025, 'colour' => 'Obsidian black', 'registration' => 'CA 101-BW', 'vehicle_class' => 'sedan', 'seats' => 3, 'ownership' => 'company']),
            'suv' => Vehicle::create(['make' => 'Audi', 'model' => 'Q7', 'year' => 2024, 'colour' => 'Mythos black', 'registration' => 'CA 202-BW', 'vehicle_class' => 'suv', 'seats' => 6, 'ownership' => 'operator']),
            'armoured' => Vehicle::create(['make' => 'Toyota', 'model' => 'Land Cruiser 300 (B6)', 'year' => 2024, 'colour' => 'Black', 'registration' => 'CA 303-BW', 'vehicle_class' => 'armoured_suv', 'seats' => 5, 'is_armoured' => true, 'ownership' => 'company']),
            'van' => Vehicle::create(['make' => 'Mercedes-Benz', 'model' => 'V-Class', 'year' => 2025, 'colour' => 'Graphite grey', 'registration' => 'CA 404-BW', 'vehicle_class' => 'van', 'seats' => 7, 'ownership' => 'company']),
        ];
        $vehicles['suv']->features()->sync([$f('suv'), $f('seven-seater')]);
        $vehicles['armoured']->features()->sync([$f('armoured'), $f('suv')]);
        $vehicles['van']->features()->sync([$f('seven-seater')]);
        foreach ($vehicles as $key => $v) {
            $v->documents()->create(['document_type_id' => $dt('licence-disc')->id, 'number' => 'LD-'.$v->id.'2026', 'expires_on' => now()->addMonths(9), 'status' => 'approved', 'reviewed_at' => now()]);
            $v->documents()->create(['document_type_id' => $dt('vehicle-insurance')->id, 'number' => 'POL-'.$v->id.'88', 'expires_on' => now()->addMonths(11), 'status' => 'approved', 'reviewed_at' => now()]);
            if ($key === 'armoured') {
                $v->documents()->create(['document_type_id' => $dt('armour-cert')->id, 'number' => 'CEN-B6-0303', 'expires_on' => now()->addYears(2), 'status' => 'approved', 'reviewed_at' => now()]);
            }
        }

        $ops = [
            // name, phone, call sign, employment, roles, gender, features, lat, lng, vehicle
            ['Sipho Ndlovu', '+27820000101', 'BW-01', 'employee', 'driver,guard', 'male', ['armed', 'advanced-driving', 'lang-isixhosa'], -33.9036, 18.4207, 'sedan'],
            ['Jacques van der Merwe', '+27820000102', 'BW-02', 'contractor', 'driver', 'male', ['advanced-driving', 'lang-afrikaans'], -33.9150, 18.3900, 'suv'],
            ['Thandi Mokoena', '+27820000103', 'BW-03', 'employee', 'guard', 'female', ['female', 'armed', 'medic'], -33.9800, 18.4650, null],
            ['Ruan Botha', '+27820000104', 'BW-04', 'contractor', 'driver,guard', 'male', ['armed', 'lang-afrikaans'], -33.8930, 18.5110, 'armoured'],
            ['Amara Diallo', '+27820000105', 'BW-05', 'contractor', 'guard', 'female', ['female', 'lang-french', 'lang-portuguese'], -33.9510, 18.3780, null],
            ['Lwazi Mthembu', '+27820000106', 'BW-06', 'employee', 'driver', 'male', ['lang-isixhosa'], -33.9249, 18.4241, 'van'],
        ];
        $profiles = [];
        foreach ($ops as $i => [$name, $phone, $sign, $employment, $roles, $gender, $featureSlugs, $lat, $lng, $vehicleKey]) {
            $user = User::create(['name' => $name, 'phone' => $phone, 'email' => strtolower(str_replace(' ', '.', $name)).'@crew.example', 'type' => 'operator', 'status' => 'active', 'phone_verified_at' => now()]);
            $op = OperatorProfile::create([
                'user_id' => $user->id, 'region_id' => $region->id, 'call_sign' => $sign, 'employment_type' => $employment, 'roles' => $roles, 'gender' => $gender,
                'psira_number' => '3'.str_pad((string) (481200 + $i), 6, '0'), 'psira_grade' => str_contains($roles, 'guard') ? 'B' : 'C', 'hired_on' => now()->subMonths(6 + $i),
                'status' => 'active', 'is_online' => true, 'online_since' => now(), 'last_lat' => $lat, 'last_lng' => $lng, 'last_location_at' => now(),
                'current_vehicle_id' => $vehicleKey ? $vehicles[$vehicleKey]->id : null,
                'pay_rate_hourly' => $employment === 'contractor' ? 180 : 0, 'pay_rate_km' => $employment === 'contractor' ? 3 : 0,
            ]);
            $op->features()->sync(array_map($f, $featureSlugs));
            $doc = fn ($slug, $months, $status = 'approved') => $op->documents()->create(['document_type_id' => $dt($slug)->id, 'number' => strtoupper(substr($slug, 0, 3)).'-'.$op->id.rand(100, 999), 'expires_on' => now()->addMonths($months), 'status' => $status, 'reviewed_at' => now()]);
            $doc('psira', 10);
            $doc('criminal-record', 18);
            if (str_contains($roles, 'driver')) {
                $doc('drivers-licence', 30);
                $doc('prdp', 14);
            }
            if (in_array('armed', $featureSlugs, true)) {
                $doc('firearm-competency', $sign === 'BW-04' ? 0 : 20); // Ruan's expires this month: shows the reminder flow
                $doc('firearm-licence', 26);
            }
            if (in_array('medic', $featureSlugs, true)) {
                $op->documents()->create(['document_type_id' => $dt('first-aid')->id, 'number' => 'FA-'.$op->id, 'expires_on' => now()->subDays(12), 'status' => 'approved', 'reviewed_at' => now()]); // expired: "Medic-trained" auto-suspended
            }
            if (in_array('advanced-driving', $featureSlugs, true)) {
                $op->documents()->create(['document_type_id' => $dt('advanced-driving-cert')->id, 'number' => 'ADV-'.$op->id, 'status' => 'approved', 'reviewed_at' => now()]);
            }
            $profiles[$sign] = $op;
        }
        // Ruan's competency is valid until the end of this month so he can still work today.
        $profiles['BW-04']->documents()->whereHas('type', fn ($q) => $q->where('slug', 'firearm-competency'))->update(['expires_on' => now()->addDays(18)]);
        $vehicles['suv']->update(['owner_operator_id' => $profiles['BW-02']->id]);

        // Clients
        $client = User::create(['name' => 'Demo Client', 'phone' => '+27820000001', 'email' => 'client@example.com', 'type' => 'client', 'status' => 'active', 'phone_verified_at' => now(), 'email_verified_at' => now(), 'accepted_terms_at' => now(), 'accepted_terms_version' => settings('legal.terms_version')]);
        $client->clientProfile()->create(['verification_status' => 'verified', 'verified_at' => now(), 'trust_level' => 'trusted', 'code_name' => 'Falcon']);
        $card = $client->paymentMethods()->create(['gateway' => 'manual', 'brand' => 'Card on file', 'last4' => '0000', 'is_default' => true, 'reusable' => true, 'verified_at' => now()]);
        $client->emergencyContacts()->create(['name' => 'Demo Contact', 'phone' => '+27820000009', 'relationship' => 'Partner']);

        $newClient = User::create(['name' => 'New Client (unverified)', 'phone' => '+27820000003', 'email' => 'new.client@example.com', 'type' => 'client', 'status' => 'active', 'phone_verified_at' => now(), 'accepted_terms_at' => now()]);
        $newClient->clientProfile()->create(['verification_status' => 'unverified']);

        $company = Company::create(['name' => 'Atlantic Capital (Demo)', 'billing_email' => 'accounts@example.com', 'eft_approved' => true, 'payment_terms_days' => 30]);
        $booker = User::create(['name' => 'Corporate Booker', 'phone' => '+27820000002', 'email' => 'pa@example.com', 'type' => 'client', 'status' => 'active', 'phone_verified_at' => now(), 'accepted_terms_at' => now()]);
        $booker->clientProfile()->create(['verification_status' => 'verified', 'verified_at' => now(), 'trust_level' => 'trusted']);
        $company->users()->attach($booker->id, ['role' => 'admin']);

        // A completed booking (so "preferred operator" and history have something to show) and an upcoming one.
        $service = Service::query()->where('slug', 'secure-chauffeur')->first();
        $past = Booking::create([
            'reference' => Booking::generateReference(), 'client_id' => $client->id, 'booked_by_id' => $client->id, 'service_id' => $service->id,
            'region_id' => $region->id, 'mode' => 'advance', 'structure' => 'single', 'status' => BookingStatus::Completed, 'source' => 'app',
            'starts_at' => now()->subDays(6)->setTime(19, 0), 'ends_at' => now()->subDays(6)->setTime(22, 0), 'duration_minutes' => 180,
            'pickup_address' => 'One&Only, Dock Road, V&A Waterfront, Cape Town', 'pickup_lat' => -33.9083, 'pickup_lng' => 18.4140,
            'dropoff_address' => 'Camps Bay, Cape Town', 'dropoff_lat' => -33.9510, 'dropoff_lng' => 18.3780, 'estimated_km' => 9.4,
            'vehicles_count' => 1, 'guards_count' => 0, 'quoted_total' => 1835, 'final_total' => 1835, 'overtime_rate' => 480,
            'quote_breakdown' => ['lines' => [['label' => 'Call-out fee', 'amount' => 350], ['label' => '3h · vehicle & chauffeur', 'amount' => 1440]], 'total' => 1835],
            'payment_method' => 'card', 'payment_method_id' => $card->id, 'payment_status' => 'paid', 'arrival_pin' => '4821',
            'started_at' => now()->subDays(6)->setTime(19, 2), 'completed_at' => now()->subDays(6)->setTime(21, 55),
        ]);
        $past->assignments()->create(['operator_profile_id' => $profiles['BW-01']->id, 'vehicle_id' => $vehicles['sedan']->id, 'role' => 'driver', 'status' => AssignmentStatus::Done, 'responded_at' => $past->starts_at->copy()->subDay(), 'started_at' => $past->started_at, 'finished_at' => $past->completed_at]);
        $past->logEvent('booking.created', 'Demo booking');

        $upcoming = Booking::create([
            'reference' => Booking::generateReference(), 'client_id' => $client->id, 'booked_by_id' => $client->id, 'service_id' => $service->id,
            'region_id' => $region->id, 'mode' => 'advance', 'structure' => 'single', 'status' => BookingStatus::Assigned, 'source' => 'web',
            'starts_at' => now()->addDay()->setTime(9, 30), 'ends_at' => now()->addDay()->setTime(11, 30), 'duration_minutes' => 120,
            'pickup_address' => 'Cape Town International Airport (CPT)', 'pickup_lat' => -33.9715, 'pickup_lng' => 18.6021,
            'dropoff_address' => 'Bantry Bay, Cape Town', 'dropoff_lat' => -33.9285, 'dropoff_lng' => 18.3780, 'estimated_km' => 27.5,
            'vehicles_count' => 1, 'guards_count' => 0, 'quoted_total' => 1630, 'overtime_rate' => 480,
            'quote_breakdown' => ['lines' => [['label' => 'Call-out fee', 'amount' => 350], ['label' => '2h · vehicle & chauffeur', 'amount' => 960], ['label' => 'Distance · 17.5 km', 'amount' => 280], ['label' => 'Meet & greet name board', 'amount' => 250]], 'total' => 1630, 'no_show_fee' => 815, 'hold_amount' => 0],
            'payment_method' => 'card', 'payment_method_id' => $card->id, 'payment_status' => 'unpaid', 'arrival_pin' => '1907',
            'reconfirm_due_at' => now()->addDay()->setTime(6, 30), 'approved_at' => now(),
        ]);
        $upcoming->assignments()->create(['operator_profile_id' => $profiles['BW-01']->id, 'vehicle_id' => $vehicles['sedan']->id, 'role' => 'driver', 'status' => AssignmentStatus::Accepted, 'responded_at' => now()]);
        $upcoming->logEvent('booking.created', 'Demo booking');

        $this->command?->info('Demo data: client +27 82 000 0001, new client +27 82 000 0003, corporate booker +27 82 000 0002, operators +27 82 000 0101 to 0106 (login codes appear on screen while SMS is in test mode).');
    }
}
