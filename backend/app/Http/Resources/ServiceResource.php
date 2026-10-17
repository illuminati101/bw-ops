<?php

namespace App\Http\Resources;

use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Service */
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $card = $this->rateCardFor(Region::default());

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'tagline' => $this->tagline,
            'summary' => $this->summary,
            'description' => $this->description,
            'highlights' => $this->highlights ?? [],
            'ideal_for' => $this->ideal_for,
            'icon' => $this->icon,
            'image_url' => $this->image_path ? asset('storage/'.$this->image_path) : null,
            'includes_transport' => $this->includes_transport,
            'includes_protection' => $this->includes_protection,
            'default_guards' => $this->default_guards,
            'default_vehicles' => $this->default_vehicles,
            'allows_on_demand' => $this->allows_on_demand && feature_enabled('on_demand'),
            'allows_advance' => $this->allows_advance && feature_enabled('advance'),
            'structure' => $this->structure,
            'min_notice_minutes' => $this->structure === 'single'
                ? max($this->min_notice_minutes, (int) settings('booking.notice_minutes'))
                : max($this->min_notice_minutes, (int) settings('booking.notice_minutes_team')),
            'min_duration_minutes' => $this->min_duration_minutes,
            'custom_quote' => $this->custom_quote,
            'from_price' => $card ? round(max($card->minimum_charge, $card->callout_fee + $card->per_hour * $this->min_duration_minutes / 60)) : null,
            'per_hour' => $card?->per_hour,
            'extras' => $this->extras()->published()->orderBy('sort')->get()->map(fn ($e) => [
                'id' => $e->id, 'name' => $e->name, 'description' => $e->description, 'pricing' => $e->pricing, 'amount' => $e->amount,
            ]),
        ];
    }
}
