<?php

namespace App\Filament\Pages;

use App\Services\Payments\PaymentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use UnitEnum;

/** Business settings and feature switches. Everything here applies immediately to the website and both apps. */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Settings & feature switches';

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    private const ICONS = [
        'Company' => 'heroicon-o-building-office', 'Features' => 'heroicon-o-adjustments-horizontal', 'Booking rules' => 'heroicon-o-calendar',
        'Dispatch' => 'heroicon-o-map', 'Cancellations' => 'heroicon-o-x-circle', 'Strikes' => 'heroicon-o-no-symbol',
        'Payments' => 'heroicon-o-credit-card', 'Escalation' => 'heroicon-o-bell-alert', 'Apps' => 'heroicon-o-device-phone-mobile',
    ];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('settings.update');
    }

    private static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function mount(): void
    {
        $values = [];
        foreach (settings()->definitions() as $key => $def) {
            $values[self::field($key)] = settings($key);
        }
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [Tab::make('Integrations')->icon('heroicon-o-link')->schema([$this->integrations()])];
        foreach (settings()->grouped() as $group => $defs) {
            $fields = [];
            foreach ($defs as $key => [$default, $type, , $label]) {
                $help = $defs[$key][4] ?? null;
                $name = self::field($key);
                $fields[] = match ($type) {
                    'bool' => Toggle::make($name)->label($label)->helperText($help),
                    'int' => TextInput::make($name)->label($label)->numeric()->integer()->helperText($help),
                    'money' => TextInput::make($name)->label($label)->numeric()->prefix('R')->helperText($help),
                    'percent' => TextInput::make($name)->label($label)->numeric()->suffix('%')->helperText($help),
                    'text' => Textarea::make($name)->label($label)->rows(2)->helperText($help),
                    'json' => Repeater::make($name)->label($label)->helperText($help)->schema([
                        TextInput::make('hours')->label('At least this many hours before start')->numeric()->required(),
                        TextInput::make('percent')->label('Fee (% of quote)')->numeric()->suffix('%')->required(),
                    ])->columns(2)->reorderable(false)->addActionLabel('Add tier'),
                    default => TextInput::make($name)->label($label)->helperText($help),
                };
            }
            $tabs[] = Tab::make($group)->icon(self::ICONS[$group] ?? null)->schema([Section::make()->columns($group === 'Features' ? 2 : 1)->schema($fields)]);
        }

        return $schema->components([Tabs::make('settings')->tabs($tabs)->persistTabInQueryString()])->statePath('data');
    }

    private function integrations(): Section
    {
        $row = fn (string $name, bool $ok, string $okText, string $offText) => '<tr><td style="padding:6px 12px 6px 0;font-weight:600">'.$name.'</td><td>'
            .($ok ? '<span style="color:#6ee7b7">● '.$okText.'</span>' : '<span style="color:#fbbf24">● '.$offText.'</span>').'</td></tr>';
        $tw = config('bwops.integrations.twilio');

        return Section::make('Connected services')->description('These are set in the server\'s .env file, not here, because they are secret keys.')->schema([
            Placeholder::make('status')->hiddenLabel()->content(new HtmlString('<table style="font-size:.9rem">'
                .$row('Card payments (Paystack)', ! app(PaymentService::class)->isRecordOnly(), 'Connected: cards are charged for real', 'Record-only mode: charges are recorded but not collected')
                .$row('SMS & calls (Twilio)', config('bwops.integrations.sms_driver') === 'twilio' && filled($tw['sid']) && filled($tw['from']), 'Connected', 'Test mode: messages appear in the Message log only')
                .$row('Maps (Google)', filled(config('bwops.integrations.google_maps_key')), 'Google Maps', 'OpenStreetMap fallback')
                .$row('Email', config('mail.default') !== 'log', 'Sending via '.config('mail.default'), 'Test mode: written to the log file')
                .$row('Real-time (Reverb)', filled(config('broadcasting.connections.reverb.key')) && config('broadcasting.default') === 'reverb', 'Configured', 'Off: apps poll every few seconds instead')
                .'</table>')),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('save')->label('Save settings')->icon('heroicon-o-check')->action('save')->keyBindings(['mod+s'])];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $values = [];
        foreach (settings()->definitions() as $key => [$default, $type]) {
            $name = self::field($key);
            if (! array_key_exists($name, $state)) {
                continue;
            }
            $v = $state[$name];
            $values[$key] = match ($type) {
                'bool' => (bool) $v,
                'int' => (int) $v,
                'money', 'percent' => (float) $v,
                'json' => collect($v ?? [])->map(fn ($t) => ['hours' => (float) $t['hours'], 'percent' => (float) $t['percent']])->sortByDesc('hours')->values()->all(),
                default => $v,
            };
        }
        settings()->setMany($values);
        Notification::make()->title('Settings saved')->body('Changes apply immediately to the website and both apps.')->success()->send();
    }
}
