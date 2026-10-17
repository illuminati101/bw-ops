<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;

class CatalogController extends Controller
{
    public function services()
    {
        $services = Service::query()->published()->orderBy('sort')->get()->filter->isAvailable()->values();

        return ServiceResource::collection($services);
    }

    public function service(string $slug)
    {
        return new ServiceResource(Service::query()->published()->where('slug', $slug)->firstOrFail());
    }
}
