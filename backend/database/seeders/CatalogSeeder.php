<?php

namespace Database\Seeders;

use App\Models\Extra;
use App\Models\Feature;
use App\Models\Region;
use App\Models\Service;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $region = Region::query()->where('slug', 'cape-town')->firstOrFail();
        foreach (require __DIR__.'/content/services.php' as $data) {
            $rates = $data['rates'];
            $extras = $data['extras'];
            unset($data['rates'], $data['extras']);

            $service = Service::withTrashed()->firstOrNew(['slug' => $data['slug']]);
            if ($service->exists) {
                continue; // never overwrite staff edits on re-seed
            }
            $service->fill($data)->save();
            $service->publish('Initial setup');

            $card = $service->rateCards()->create(['region_id' => $region->id] + $rates);
            $card->publish('Initial setup');

            $service->extras()->sync(Extra::query()->whereIn('slug', $extras)->pluck('id'));
        }
    }
}
