<?php

namespace App\Livewire\Account;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.site')]
#[Title('Your bookings')]
class Dashboard extends Component
{
    public string $tab = 'upcoming';

    public function render()
    {
        $user = auth()->user();
        $closed = array_map(fn ($s) => $s->value, BookingStatus::closed());
        $q = Booking::query()->visibleTo($user)->with(['service', 'activeAssignments.operator.user']);

        return view('livewire.account.dashboard', [
            'user' => $user,
            'upcoming' => (clone $q)->whereNotIn('status', $closed)->orderBy('starts_at')->get(),
            'past' => (clone $q)->whereIn('status', $closed)->orderByDesc('starts_at')->limit(30)->get(),
            'verification' => $user->clientProfile?->verification_status ?? 'unverified',
            'hasCard' => $user->paymentMethods()->exists(),
        ]);
    }
}
