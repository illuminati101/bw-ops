<?php

use App\Livewire\Account;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/book', Account\BookingWizard::class)->name('book');
Route::livewire('/account/login', Account\Login::class)->name('account.login')->middleware('guest');
Route::redirect('/account/register', '/account/login')->name('account.register');

Route::middleware(['auth', 'client'])->group(function () {
    Route::livewire('/account', Account\Dashboard::class)->name('account');
    Route::livewire('/account/bookings/{reference}', Account\BookingDetail::class)->name('account.booking');
    Route::livewire('/account/profile', Account\Profile::class)->name('account.profile');
    Route::livewire('/account/payment', Account\PaymentMethods::class)->name('account.payment');
    Route::livewire('/account/verify', Account\Verify::class)->name('account.verify');
    Route::post('/account/logout', function () {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    })->name('account.logout');
});
