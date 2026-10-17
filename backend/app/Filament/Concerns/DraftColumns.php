<?php

namespace App\Filament\Concerns;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class DraftColumns
{
    public static function status(): TextColumn
    {
        return TextColumn::make('publish_state')
            ->label('Status')
            ->badge()
            ->state(function ($record) {
                if (! $record->is_published) {
                    return 'Not published';
                }
                if ($record->hasDraft()) {
                    return $record->publish_at ? 'Scheduled' : 'Draft changes';
                }

                return 'Live';
            })
            ->color(fn (string $state) => match ($state) {
                'Live' => 'success',
                'Draft changes', 'Scheduled' => 'warning',
                default => 'gray',
            })
            ->tooltip(fn ($record) => $record->publish_at ? 'Publishes '.$record->publish_at->format('D j M H:i') : null);
    }

    public static function filter(): SelectFilter
    {
        return SelectFilter::make('publish_state')
            ->label('Status')
            ->options(['live' => 'Live', 'draft' => 'Has draft changes', 'unpublished' => 'Not published'])
            ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                'live' => $query->where('is_published', true)->whereNull('draft'),
                'draft' => $query->whereNotNull('draft'),
                'unpublished' => $query->where('is_published', false),
                default => $query,
            });
    }
}
