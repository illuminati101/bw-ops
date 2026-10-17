<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\Extra;
use App\Models\Feature;
use App\Models\PublicHoliday;
use App\Models\Region;
use App\Models\SurchargeRule;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        Region::updateOrCreate(['slug' => 'cape-town'], [
            'name' => 'Cape Town', 'center_lat' => -33.9249, 'center_lng' => 18.4241, 'radius_km' => 75,
            'bounds' => [-34.40, 18.30, -33.45, 19.15], 'is_active' => true,
        ]);

        $features = [
            // name, slug, applies_to, category, client_filterable, client_label, surcharge_type, surcharge_amount, icon
            ['Armed', 'armed', 'operator', 'capability', true, 'Armed operator', 'per_hour', 120, 'shield'],
            ['Female operator', 'female', 'operator', 'capability', true, 'Female operator', 'none', 0, 'user'],
            ['Advanced / evasive driving', 'advanced-driving', 'operator', 'training', false, 'Advanced driver', 'none', 0, 'steering-wheel'],
            ['Medic-trained', 'medic', 'operator', 'training', true, 'Medic-trained', 'none', 0, 'heart-pulse'],
            ['Speaks Afrikaans', 'lang-afrikaans', 'operator', 'language', true, 'Afrikaans', 'none', 0, 'languages'],
            ['Speaks isiXhosa', 'lang-isixhosa', 'operator', 'language', true, 'isiXhosa', 'none', 0, 'languages'],
            ['Speaks French', 'lang-french', 'operator', 'language', true, 'French', 'none', 0, 'languages'],
            ['Speaks Portuguese', 'lang-portuguese', 'operator', 'language', true, 'Portuguese', 'none', 0, 'languages'],
            ['Speaks German', 'lang-german', 'operator', 'language', true, 'German', 'none', 0, 'languages'],
            ['Armoured vehicle', 'armoured', 'vehicle', 'vehicle', true, 'Armoured vehicle', 'flat', 2500, 'car'],
            ['Luxury SUV', 'suv', 'vehicle', 'vehicle', true, 'Luxury SUV', 'none', 0, 'car'],
            ['7-seater', 'seven-seater', 'vehicle', 'vehicle', true, '7 seats', 'none', 0, 'car'],
        ];
        foreach ($features as $i => [$name, $slug, $applies, $cat, $filter, $label, $sType, $sAmount, $icon]) {
            Feature::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'applies_to' => $applies, 'category' => $cat, 'client_filterable' => $filter, 'client_label' => $label,
                'surcharge_type' => $sType, 'surcharge_amount' => $sAmount, 'icon' => $icon, 'sort' => $i, 'is_active' => true,
            ]);
        }

        $docs = [
            // name, slug, applies_to, expiry, all_jobs, driving, reminder, linked feature slugs
            ['PSIRA registration certificate', 'psira', 'operator', true, true, false, 30, []],
            ['Criminal record clearance', 'criminal-record', 'operator', true, true, false, 30, []],
            ['Firearm competency certificate', 'firearm-competency', 'operator', true, false, false, 60, ['armed']],
            ['Firearm licence', 'firearm-licence', 'operator', true, false, false, 60, ['armed']],
            ['Driver\'s licence', 'drivers-licence', 'operator', true, false, true, 30, []],
            ['Professional Driving Permit (PrDP)', 'prdp', 'operator', true, false, true, 30, []],
            ['First aid certificate', 'first-aid', 'operator', true, false, false, 30, ['medic']],
            ['Advanced driving certificate', 'advanced-driving-cert', 'operator', false, false, false, 30, ['advanced-driving']],
            ['Vehicle licence disc', 'licence-disc', 'vehicle', true, true, false, 30, []],
            ['Vehicle insurance', 'vehicle-insurance', 'vehicle', true, true, false, 30, []],
            ['Armour certification', 'armour-cert', 'vehicle', true, false, false, 60, ['armoured']],
        ];
        foreach ($docs as [$name, $slug, $applies, $expiry, $all, $driving, $reminder, $featureSlugs]) {
            $type = DocumentType::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'applies_to' => $applies, 'requires_expiry' => $expiry, 'required_for_all_jobs' => $all,
                'required_for_driving' => $driving, 'reminder_days' => $reminder,
            ]);
            $type->features()->sync(Feature::query()->whereIn('slug', $featureSlugs)->pluck('id'));
        }

        foreach ([
            ['meet-and-greet', 'Meet & greet name board', 'Discreet name identification at the pickup point.', 'flat', 250],
            ['child-seat', 'Child seat', 'Age-appropriate child seat fitted before pickup.', 'flat', 150],
            ['venue-assessment', 'Venue & route assessment', 'An officer visits the venue and plans routes in advance.', 'flat', 1500],
        ] as $i => [$slug, $name, $desc, $pricing, $amount]) {
            $extra = Extra::updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => $desc, 'pricing' => $pricing, 'amount' => $amount, 'sort' => $i]);
            if (! $extra->is_published) {
                $extra->publish('Initial setup');
            }
        }

        SurchargeRule::updateOrCreate(['kind' => 'night'], ['name' => 'Late-night surcharge (22:00–05:00)', 'starts_at' => '22:00', 'ends_at' => '05:00', 'percent' => 20, 'is_active' => true]);
        SurchargeRule::updateOrCreate(['kind' => 'holiday'], ['name' => 'Public holiday surcharge', 'percent' => 25, 'is_active' => true]);
        SurchargeRule::updateOrCreate(['kind' => 'weekend'], ['name' => 'Weekend surcharge', 'percent' => 10, 'is_active' => false]);

        $holidays = [
            '2026-01-01' => 'New Year\'s Day', '2026-03-21' => 'Human Rights Day', '2026-04-03' => 'Good Friday', '2026-04-06' => 'Family Day',
            '2026-04-27' => 'Freedom Day', '2026-05-01' => 'Workers\' Day', '2026-06-16' => 'Youth Day', '2026-08-10' => 'National Women\'s Day (observed)',
            '2026-09-24' => 'Heritage Day', '2026-12-16' => 'Day of Reconciliation', '2026-12-25' => 'Christmas Day', '2026-12-26' => 'Day of Goodwill',
            '2027-01-01' => 'New Year\'s Day', '2027-03-22' => 'Human Rights Day (observed)', '2027-03-26' => 'Good Friday', '2027-03-29' => 'Family Day',
            '2027-04-27' => 'Freedom Day', '2027-05-01' => 'Workers\' Day', '2027-06-16' => 'Youth Day', '2027-08-09' => 'National Women\'s Day',
            '2027-09-24' => 'Heritage Day', '2027-12-16' => 'Day of Reconciliation', '2027-12-25' => 'Christmas Day', '2027-12-27' => 'Day of Goodwill (observed)',
        ];
        foreach ($holidays as $date => $name) {
            PublicHoliday::updateOrCreate(['date' => $date], ['name' => $name]);
        }
    }
}
