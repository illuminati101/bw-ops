<?php

namespace App\Filament\Resources\RateCards;

use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\DraftColumns;
use App\Models\RateCard;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RateCardResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'catalog';

    protected static ?string $model = RateCard::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Services & pricing';

    protected static ?string $navigationLabel = 'Rate cards';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false; // created automatically with each service
    }

    public static function form(Schema $schema): Schema
    {
        $money = fn (string $name, string $label, ?string $help = null) => TextInput::make($name)->label($label)->numeric()->prefix('R')->minValue(0)->step(0.01)->helperText($help);

        return $schema->components([
            Section::make(fn ($record) => $record?->service?->name.' · '.$record?->region?->name)
                ->description('Prices include VAT if your settings say so. Changes are saved as a draft until you publish.')
                ->columns(3)
                ->schema([
                    $money('callout_fee', 'Call-out fee', 'Charged once per vehicle (or per officer for protection-only).'),
                    $money('per_hour', 'Per hour', 'Per vehicle with chauffeur, or per officer.'),
                    $money('per_km', 'Per km', 'Transport services only.'),
                    $money('per_extra_guard_hour', 'Additional officer per hour', 'Officers beyond the service default.'),
                    $money('minimum_charge', 'Minimum charge', 'Per booking (per vehicle for multi-vehicle).'),
                    $money('overtime_per_hour', 'Overtime per hour', 'Leave blank to use the hourly rate.'),
                    TextInput::make('included_km')->numeric()->label('Included km')->suffix('km'),
                    TextInput::make('included_wait_minutes')->numeric()->label('Grace before overtime')->suffix('min'),
                ]),
            Section::make('Example')->schema([
                Placeholder::make('example')->hiddenLabel()->content(function ($get, $record) {
                    $h = max(1, ($record?->service?->min_duration_minutes ?? 60) / 60);
                    $total = (float) $get('callout_fee') + (float) $get('per_hour') * $h + ($record?->service?->includes_transport ? max(0, 20 - (float) $get('included_km')) * (float) $get('per_km') : 0);
                    $total = max($total, (float) $get('minimum_charge'));

                    return 'A '.rtrim(rtrim(number_format($h, 1), '0'), '.').'-hour booking'.($record?->service?->includes_transport ? ' covering 20 km' : '').' would be quoted at about '.rands(ceil($total / 5) * 5, false).' before surcharges and extras.';
                }),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service.name')->searchable()->weight('bold'),
                TextColumn::make('region.name'),
                TextColumn::make('callout_fee')->money('ZAR')->label('Call-out'),
                TextColumn::make('per_hour')->money('ZAR')->label('Per hour'),
                TextColumn::make('per_km')->money('ZAR')->label('Per km'),
                TextColumn::make('minimum_charge')->money('ZAR')->label('Minimum'),
                DraftColumns::status(),
            ])
            ->filters([DraftColumns::filter()])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRateCards::route('/'),
            'edit' => Pages\EditRateCard::route('/{record}/edit'),
        ];
    }
}
