<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasLabel, HasColor
{
    case PendingApproval = 'pending_approval';
    case QuoteRequested = 'quote_requested';
    case Quoted = 'quoted';
    case Confirmed = 'confirmed';
    case Searching = 'searching';
    case Assigned = 'assigned';
    case EnRoute = 'en_route';
    case Arrived = 'arrived';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Declined = 'declined';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingApproval => 'Awaiting approval',
            self::QuoteRequested => 'Quote requested',
            self::Quoted => 'Quote sent',
            self::Confirmed => 'Confirmed',
            self::Searching => 'Finding operator',
            self::Assigned => 'Operator assigned',
            self::EnRoute => 'On the way',
            self::Arrived => 'Arrived',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No-show',
            self::Declined => 'Declined',
            self::Expired => 'No operator found',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingApproval, self::QuoteRequested, self::Quoted => 'warning',
            self::Confirmed, self::Assigned => 'info',
            self::Searching, self::EnRoute, self::Arrived, self::InProgress => 'primary',
            self::Completed => 'success',
            self::Cancelled, self::Declined, self::Expired => 'gray',
            self::NoShow => 'danger',
        };
    }

    /** Statuses where the job is live or about to be. */
    public static function active(): array
    {
        return [self::Searching, self::Assigned, self::EnRoute, self::Arrived, self::InProgress];
    }

    /** Statuses that still occupy future capacity. */
    public static function open(): array
    {
        return [self::PendingApproval, self::QuoteRequested, self::Quoted, self::Confirmed, ...self::active()];
    }

    public static function closed(): array
    {
        return [self::Completed, self::Cancelled, self::NoShow, self::Declined, self::Expired];
    }

    public function isClosed(): bool
    {
        return in_array($this, self::closed(), true);
    }

    public function isCancellable(): bool
    {
        return ! $this->isClosed() && $this !== self::InProgress;
    }
}
