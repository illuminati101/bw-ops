<?php

namespace App\Filament\Resources\JobApplications;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\JobApplication;
use App\Models\OperatorProfile;
use App\Models\User;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class JobApplicationResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'enquiries';

    protected static ?string $model = JobApplication::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Job applications';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = JobApplication::query()->where('status', 'new')->count();

        return $n ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Placeholder::make('applicant')->content(fn ($record) => "{$record->name} · {$record->phone} · {$record->email}"),
            Placeholder::make('role')->content(fn ($record) => ucfirst($record->position)." · {$record->years_experience} years · PSIRA {$record->psira_number} ({$record->psira_grade})"),
            Placeholder::make('message')->content(fn ($record) => $record->message ?: '—')->columnSpanFull(),
            Placeholder::make('files')->content(fn ($record) => new HtmlString(collect($record->files ?? [])->map(fn ($path, $i) => '<a class="underline" target="_blank" href="'.route('staff.files.application', [$record, $i]).'">'.e(basename($path)).'</a>')->implode(' · ') ?: '—'))->columnSpanFull(),
            Select::make('status')->options(['new' => 'New', 'reviewing' => 'Reviewing', 'interview' => 'Interview', 'hired' => 'Hired', 'rejected' => 'Rejected'])->native(false),
            Textarea::make('staff_notes')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('created_at')->since()->label('Applied'),
            TextColumn::make('name')->weight('bold')->searchable()->description(fn ($record) => $record->phone),
            TextColumn::make('position')->badge(),
            TextColumn::make('years_experience')->label('Years'),
            IconColumn::make('firearm_competent')->boolean()->label('Firearm'),
            IconColumn::make('has_prdp')->boolean()->label('PrDP'),
            TextColumn::make('status')->badge()->color(fn ($state) => ['new' => 'warning', 'hired' => 'success', 'rejected' => 'gray'][$state] ?? 'info'),
        ])->filters([SelectFilter::make('status')->options(['new' => 'New', 'reviewing' => 'Reviewing', 'interview' => 'Interview', 'hired' => 'Hired', 'rejected' => 'Rejected'])])
            ->recordActions([
                EditAction::make()->label('Open'),
                Action::make('hire')->label('Create operator')->icon('heroicon-o-user-plus')->color('success')
                    ->visible(fn ($record) => $record->status !== 'hired' && auth()->user()->can('operators.update'))
                    ->requiresConfirmation()->modalDescription('Creates an inactive operator profile from this application. Add their documents, then activate them.')
                    ->action(function ($record) {
                        $phone = PhoneNumber::normalize($record->phone);
                        if (User::withTrashed()->where('phone', $phone)->exists()) {
                            Notification::make()->title('This number already has an account')->danger()->send();

                            return;
                        }
                        $user = User::create(['name' => $record->name, 'phone' => $phone, 'email' => $record->email, 'type' => 'operator', 'status' => 'active']);
                        $op = OperatorProfile::create([
                            'user_id' => $user->id, 'status' => 'inactive', 'employment_type' => 'contractor',
                            'roles' => match ($record->position) { 'driver' => 'driver', 'protection' => 'guard', default => 'driver,guard' },
                            'psira_number' => $record->psira_number, 'psira_grade' => $record->psira_grade, 'region_id' => \App\Models\Region::query()->value('id'),
                        ]);
                        $record->update(['status' => 'hired']);
                        Notification::make()->title('Operator created')->success()->send();
                        redirect(\App\Filament\Resources\Operators\OperatorResource::getUrl('edit', ['record' => $op]));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListJobApplications::route('/'), 'edit' => Pages\EditJobApplication::route('/{record}/edit')];
    }
}
