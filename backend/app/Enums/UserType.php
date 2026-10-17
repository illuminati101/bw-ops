<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserType: string implements HasLabel
{
    case Staff = 'staff';
    case Operator = 'operator';
    case Client = 'client';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
