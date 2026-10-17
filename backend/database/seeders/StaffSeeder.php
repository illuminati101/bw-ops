<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@blackandwhiteops.com');
        $owner = User::query()->where('email', $email)->first();
        if (! $owner) {
            $password = env('ADMIN_PASSWORD', 'ChangeMe-2026!');
            $owner = User::create([
                'name' => env('ADMIN_NAME', 'Owner'), 'email' => $email, 'phone' => env('ADMIN_PHONE'),
                'type' => 'staff', 'status' => 'active', 'password' => $password, 'email_verified_at' => now(),
            ]);
            $this->command?->warn("Owner login: {$email} / {$password}  (change this after first login)");
        }
        $owner->syncRoles(['Owner']);
    }
}
