<?php

namespace App\Services\Compliance;

use App\Models\DocumentType;
use App\Models\Feature;
use App\Models\OperatorProfile;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Works out what an operator (or vehicle) is currently allowed to do, based on their documents.
 * A feature such as "Armed" only counts while every document type linked to it is valid.
 */
class ComplianceService
{
    /** @return array<int, int> */
    public function validDocumentTypeIds(OperatorProfile|Vehicle $subject): array
    {
        $docs = $subject->relationLoaded('documents') ? $subject->documents : $subject->documents()->get();

        return $docs->filter->isValid()->pluck('document_type_id')->unique()->values()->all();
    }

    /** @return Collection<int, Feature> features the operator holds AND is currently compliant for */
    public function effectiveFeatures(OperatorProfile|Vehicle $subject): Collection
    {
        $features = ($subject->relationLoaded('features') ? $subject->features : $subject->features()->get())
            ->where('is_active', true);
        $valid = $this->validDocumentTypeIds($subject);
        $requirements = $this->requirementsMap();

        return $features->filter(function (Feature $f) use ($valid, $requirements) {
            $needed = $requirements[$f->id] ?? [];

            return array_diff($needed, $valid) === [];
        })->values();
    }

    /** @return array<int, int> */
    public function effectiveFeatureIds(OperatorProfile|Vehicle $subject): array
    {
        return $this->effectiveFeatures($subject)->pluck('id')->all();
    }

    /** Features the operator holds but which are suspended because a document lapsed. */
    public function suspendedFeatures(OperatorProfile|Vehicle $subject): Collection
    {
        $effective = $this->effectiveFeatureIds($subject);

        return $subject->features()->get()->reject(fn ($f) => in_array($f->id, $effective, true))->values();
    }

    public function canWork(OperatorProfile $op): bool
    {
        if ($op->status !== 'active' || $op->user?->status !== 'active') {
            return false;
        }

        return $this->missingTypes($op, DocumentType::query()->where('applies_to', 'operator')->where('required_for_all_jobs', true)->get())->isEmpty();
    }

    public function canDrive(OperatorProfile $op): bool
    {
        return $op->hasRole('driver')
            && $this->missingTypes($op, DocumentType::query()->where('applies_to', 'operator')->where('required_for_driving', true)->get())->isEmpty();
    }

    public function vehicleRoadworthy(?Vehicle $v): bool
    {
        if (! $v || $v->status !== 'active') {
            return false;
        }

        return $this->missingTypes($v, DocumentType::query()->where('applies_to', 'vehicle')->where('required_for_all_jobs', true)->get())->isEmpty();
    }

    /** Human-readable list of problems, for the admin panel and Crew app. */
    public function issues(OperatorProfile $op): array
    {
        $issues = [];
        foreach ($this->missingTypes($op, DocumentType::query()->where('applies_to', 'operator')->where('required_for_all_jobs', true)->get()) as $t) {
            $issues[] = "{$t->name} missing or expired: cannot be assigned to any job";
        }
        if ($op->hasRole('driver')) {
            foreach ($this->missingTypes($op, DocumentType::query()->where('applies_to', 'operator')->where('required_for_driving', true)->get()) as $t) {
                $issues[] = "{$t->name} missing or expired: cannot drive";
            }
        }
        foreach ($this->suspendedFeatures($op) as $f) {
            $issues[] = "\"{$f->name}\" suspended until documents are renewed";
        }
        if ($op->hasRole('driver') && $op->current_vehicle_id && ! $this->vehicleRoadworthy($op->currentVehicle)) {
            $issues[] = 'Current vehicle has missing or expired documents';
        }

        return $issues;
    }

    private function missingTypes(OperatorProfile|Vehicle $subject, Collection $types): Collection
    {
        $valid = $this->validDocumentTypeIds($subject);

        return $types->reject(fn ($t) => in_array($t->id, $valid, true))->values();
    }

    /** @return array<int, array<int, int>> feature id => required document type ids */
    private function requirementsMap(): array
    {
        static $cache = null;
        if ($cache !== null && ! app()->runningUnitTests()) {
            return $cache;
        }
        $map = [];
        foreach (\DB::table('document_type_feature')->get() as $row) {
            $map[$row->feature_id][] = $row->document_type_id;
        }

        return $cache = $map;
    }
}
