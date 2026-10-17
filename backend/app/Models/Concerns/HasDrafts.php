<?php

namespace App\Models\Concerns;

use App\Models\Revision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Draft → preview → publish workflow.
 *
 * - Records that have never been published are edited directly (nobody can see them yet).
 * - Once published, edits are stored in the `draft` JSON column and the live columns keep
 *   serving the website and apps until someone publishes (now, or at `publish_at`).
 * - Every publish stores a snapshot in `revisions`, so any earlier version can be restored.
 */
trait HasDrafts
{
    public static function bootHasDrafts(): void
    {
        static::saving(function ($model) {
            if (is_array($model->draft) && $model->draft === []) {
                $model->draft = null;
            }
        });
    }

    public function initializeHasDrafts(): void
    {
        $this->mergeCasts([
            'draft' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'publish_at' => 'datetime',
        ]);
    }

    /** Attributes that go through the draft workflow. */
    public function draftableAttributes(): array
    {
        return array_values(array_diff($this->getFillable(), ['draft', 'is_published', 'published_at', 'publish_at', 'published_by']));
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->getTable().'.is_published', true);
    }

    public function hasDraft(): bool
    {
        return ! empty($this->draft);
    }

    /** Save edits: straight to live columns if unpublished, otherwise into the draft. */
    public function saveDraft(array $data): static
    {
        $data = Arr::only($data, $this->draftableAttributes());

        if (! $this->is_published) {
            $this->fill($data)->save();

            return $this;
        }

        $changes = [];
        $probe = $this->replicate();
        $probe->fill($data);
        foreach ($data as $key => $value) {
            $live = $this->getAttributes()[$key] ?? null;
            $new = $probe->getAttributes()[$key] ?? null;
            if ($this->normalizeForCompare($live) !== $this->normalizeForCompare($new)) {
                $changes[$key] = $probe->getAttribute($key) instanceof \BackedEnum ? $probe->getAttribute($key)->value : $probe->getAttribute($key);
            }
        }

        $this->draft = $changes ?: null;
        $this->saveQuietly();

        return $this;
    }

    /** A copy of the model with the draft applied (for previews and edit forms). */
    public function withDraftApplied(): static
    {
        $copy = clone $this;
        if ($this->hasDraft()) {
            $copy->forceFill($this->draft);
        }

        return $copy;
    }

    public function publish(?string $note = null): static
    {
        DB::transaction(function () use ($note) {
            if ($this->hasDraft()) {
                $this->forceFill($this->draft);
            }
            $this->draft = null;
            $this->is_published = true;
            $this->published_at = now();
            $this->publish_at = null;
            $this->published_by = Auth::id();
            $this->save();

            $this->revisions()->create([
                'snapshot' => Arr::only($this->attributesToArray(), $this->draftableAttributes()),
                'note' => $note,
                'user_id' => Auth::id(),
                'created_at' => now(),
            ]);
        });

        return $this;
    }

    public function schedulePublish(\DateTimeInterface $when): static
    {
        $this->publish_at = $when;
        $this->saveQuietly();

        return $this;
    }

    public function discardDraft(): static
    {
        $this->draft = null;
        $this->publish_at = null;
        $this->saveQuietly();

        return $this;
    }

    public function unpublish(): static
    {
        $this->is_published = false;
        $this->save();

        return $this;
    }

    /** Load an earlier revision into the draft so it can be previewed, then published. */
    public function restoreRevision(Revision $revision): static
    {
        if (! $this->is_published) {
            $this->fill(Arr::only($revision->snapshot, $this->draftableAttributes()))->save();

            return $this;
        }

        return $this->saveDraft($revision->snapshot);
    }

    private function normalizeForCompare(mixed $value): mixed
    {
        if (is_string($value) && ($decoded = json_decode($value, true)) !== null && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
            return json_encode($decoded);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_numeric($value)) {
            return (string) (0 + $value);
        }

        return $value === '' ? null : $value;
    }
}
