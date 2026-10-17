<?php

namespace App\Filament\Resources\Operators\Pages;

use App\Filament\Resources\Operators\OperatorResource;
use App\Models\OperatorProfile;
use App\Models\User;
use App\Support\PhoneNumber;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateOperator extends CreateRecord
{
    protected static string $resource = OperatorResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $phone = PhoneNumber::normalize($data['user_phone']);
        if (! $phone) {
            throw ValidationException::withMessages(['data.user_phone' => 'Please enter a valid mobile number.']);
        }
        if (User::withTrashed()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['data.user_phone' => 'This number already belongs to another account.']);
        }
        $user = User::create([
            'name' => $data['user_name'], 'phone' => $phone, 'email' => $data['user_email'] ?? null,
            'avatar_path' => $data['user_avatar'] ?? null, 'type' => 'operator', 'status' => 'active',
        ]);
        $profile = OperatorProfile::create(collect($data)->except(['user_name', 'user_phone', 'user_email', 'user_avatar', 'features'])->all() + ['user_id' => $user->id]);

        return $profile;
    }

    protected function getRedirectUrl(): string
    {
        return OperatorResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
