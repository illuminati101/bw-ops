<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Edit-page behaviour for draftable records: saving stores a draft (once published),
 * and header actions publish now / schedule / discard / unpublish / restore a revision.
 *
 * Expects static::$publishPermission on the page, e.g. "catalog.publish".
 */
trait InteractsWithDrafts
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record->hasDraft()) {
            $data = array_merge($data, $record->draft);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $wasPublished = $record->is_published;
        $record->saveDraft($data);
        if ($wasPublished && $record->fresh()->hasDraft()) {
            Notification::make()->title('Draft saved')->body('The live version is unchanged until you publish.')->info()->send();
        }

        return $record;
    }

    protected function getSavedNotification(): ?Notification
    {
        return $this->getRecord()->is_published && $this->getRecord()->hasDraft() ? null : parent::getSavedNotification();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $r = $this->getRecord();
        if (! $r->is_published) {
            return new HtmlString('<span class="bw-draft-banner">Not published yet: not visible on the website or in the apps.</span>');
        }
        if ($r->hasDraft()) {
            $when = $r->publish_at ? ' · scheduled to publish '.$r->publish_at->format('D j M Y H:i') : '';

            return new HtmlString('<span class="bw-draft-banner">Unpublished changes: '.e(implode(', ', array_map(fn ($k) => str_replace('_', ' ', $k), array_keys($r->draft)))).$when.'</span>');
        }

        return 'Live · last published '.($r->published_at?->diffForHumans() ?? 'n/a');
    }

    protected function canPublish(): bool
    {
        $u = auth()->user();

        return $u && ($u->hasRole('Owner') || $u->can(static::$publishPermission));
    }

    protected function draftActions(): array
    {
        return [
            Action::make('publish')
                ->label(fn () => $this->getRecord()->is_published ? 'Publish changes' : 'Publish')
                ->icon('heroicon-o-rocket-launch')
                ->color('primary')
                ->visible(fn () => $this->canPublish() && (! $this->getRecord()->is_published || $this->getRecord()->hasDraft()))
                ->schema([
                    TextInput::make('note')->label('What changed? (for the history)')->maxLength(200),
                ])
                ->requiresConfirmation()
                ->modalDescription('This goes live on the website and in both apps immediately.')
                ->action(function (array $data) {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->getRecord()->refresh()->publish($data['note'] ?? null);
                    $this->refreshFormData(array_keys($this->getRecord()->attributesToArray()));
                    Notification::make()->title('Published')->success()->send();
                }),
            ActionGroup::make([
                Action::make('schedule')
                    ->label('Schedule publish')
                    ->icon('heroicon-o-clock')
                    ->visible(fn () => $this->canPublish() && $this->getRecord()->is_published && $this->getRecord()->hasDraft())
                    ->schema([DateTimePicker::make('when')->required()->minDate(now())->seconds(false)->native(false)])
                    ->action(function (array $data) {
                        $this->getRecord()->schedulePublish(\Carbon\Carbon::parse($data['when']));
                        Notification::make()->title('Scheduled')->body('Changes will publish automatically.')->success()->send();
                    }),
                Action::make('discard')
                    ->label('Discard draft')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn () => $this->getRecord()->hasDraft())
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->getRecord()->discardDraft();
                        $this->fillForm();
                        Notification::make()->title('Draft discarded')->success()->send();
                    }),
                Action::make('history')
                    ->label('History & restore')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn () => view('filament.revisions', ['record' => $this->getRecord(), 'page' => $this])),
                Action::make('unpublish')
                    ->label('Unpublish (hide)')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->visible(fn () => $this->canPublish() && $this->getRecord()->is_published)
                    ->requiresConfirmation()
                    ->modalDescription('It will disappear from the website and apps until published again.')
                    ->action(function () {
                        $this->getRecord()->unpublish();
                        Notification::make()->title('Unpublished')->success()->send();
                    }),
            ])->label('More')->icon('heroicon-m-ellipsis-vertical')->button()->color('gray'),
        ];
    }

    public function restoreRevision(int $revisionId): void
    {
        $record = $this->getRecord();
        $revision = $record->revisions()->findOrFail($revisionId);
        $record->restoreRevision($revision);
        $this->fillForm();
        Notification::make()->title('Revision loaded as a draft')->body('Review it, then publish to make it live.')->success()->send();
        $this->unmountAction();
    }
}
