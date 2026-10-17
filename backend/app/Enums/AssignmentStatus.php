<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssignmentStatus: string implements HasLabel, HasColor
{
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case EnRoute = 'en_route';
    case Arrived = 'arrived';
    case OnJob = 'on_job';
    case Done = 'done';
    case Removed = 'removed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Assigned => 'Awaiting acceptance',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::EnRoute => 'En route',
            self::Arrived => 'Arrived',
            self::OnJob => 'On job',
            self::Done => 'Done',
            self::Removed => 'Removed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Assigned => 'warning',
            self::Accepted => 'info',
            self::EnRoute, self::Arrived, self::OnJob => 'primary',
            self::Done => 'success',
            self::Declined, self::Removed => 'gray',
        };
    }

    public static function live(): array
    {
        return [self::Assigned, self::Accepted, self::EnRoute, self::Arrived, self::OnJob];
    }
}
