<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/** Maps Filament abilities onto our permission names, e.g. "vehicles.view" / "vehicles.update". */
trait ChecksPermissions
{
    protected static function allowed(string $action): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('Owner') || $user->can(static::$permissionPrefix.'.'.$action);
    }

    public static function canViewAny(): bool
    {
        return static::allowed('view');
    }

    public static function canView(Model $record): bool
    {
        return static::allowed('view');
    }

    public static function canCreate(): bool
    {
        return static::allowed('update');
    }

    public static function canEdit(Model $record): bool
    {
        return static::allowed('update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed('update');
    }

    public static function canDeleteAny(): bool
    {
        return static::allowed('update');
    }
}
