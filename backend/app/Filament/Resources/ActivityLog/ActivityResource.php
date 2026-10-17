<?php

namespace App\Filament\Resources\ActivityLog;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'audit-log';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('audit.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->modifyQueryUsing(fn ($query) => $query->with('causer'))->columns([
            TextColumn::make('created_at')->dateTime('D j M Y H:i:s')->label('When'),
            TextColumn::make('causer.name')->label('Who')->placeholder('System / client app'),
            TextColumn::make('event')->badge()->color(fn ($state) => ['created' => 'success', 'updated' => 'info', 'deleted' => 'danger'][$state] ?? 'gray'),
            TextColumn::make('subject_type')->label('Record')->formatStateUsing(fn ($state, $record) => str_replace('_', ' ', (string) $state).' #'.$record->subject_id),
            TextColumn::make('changes')->label('Changes')->wrap()->state(function ($record) {
                $new = $record->properties['attributes'] ?? [];
                $old = $record->properties['old'] ?? [];

                return collect($new)->map(fn ($v, $k) => $k.': '.(is_array($old[$k] ?? null) ? json_encode($old[$k]) : ($old[$k] ?? '∅')).' → '.(is_array($v) ? json_encode($v) : $v))->take(6)->implode(' | ');
            })->limit(160),
        ])->filters([SelectFilter::make('event')->options(['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted'])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListActivities::route('/')];
    }
}
