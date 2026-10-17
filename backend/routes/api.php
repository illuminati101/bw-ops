<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\Crew\CrewController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\PlacesController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\SosController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    // Public
    Route::get('config', ConfigController::class)->name('config');
    Route::get('services', [CatalogController::class, 'services'])->name('services.index');
    Route::get('services/{slug}', [CatalogController::class, 'service'])->name('services.show');
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('places/search', [PlacesController::class, 'search'])->name('places.search');
        Route::get('places/details', [PlacesController::class, 'details'])->name('places.details');
        Route::get('places/reverse', [PlacesController::class, 'reverse'])->name('places.reverse');
        Route::post('quotes', QuoteController::class)->name('quotes');
    });
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/otp/request', [AuthController::class, 'requestCode'])->name('auth.request');
        Route::post('auth/otp/verify', [AuthController::class, 'verifyCode'])->name('auth.verify');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('broadcasting/auth', fn (Request $request) => Broadcast::auth($request))->name('broadcasting.auth');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [MeController::class, 'show'])->name('me.show');
        Route::patch('me', [MeController::class, 'update'])->name('me.update');
        Route::post('me/avatar', [MeController::class, 'avatar'])->name('me.avatar');
        Route::post('me/devices', [MeController::class, 'registerDevice'])->name('me.devices');
        Route::post('me/safety-pins', [SosController::class, 'setPins'])->name('me.pins');
        Route::post('sos', [SosController::class, 'store'])->name('sos.store')->middleware('throttle:20,1');
        Route::patch('sos/{id}', [SosController::class, 'update'])->name('sos.update');
        Route::post('sos/{id}/cancel', [SosController::class, 'cancel'])->name('sos.cancel');

        // Client app / website
        Route::middleware('abilities:client')->group(function () {
            Route::delete('me', [MeController::class, 'destroy'])->name('me.destroy');
            Route::post('me/verification', [MeController::class, 'verification'])->name('me.verification');
            Route::get('me/contacts', [MeController::class, 'contacts'])->name('me.contacts');
            Route::post('me/contacts', [MeController::class, 'storeContact'])->name('me.contacts.store');
            Route::delete('me/contacts/{id}', [MeController::class, 'destroyContact'])->name('me.contacts.destroy');
            Route::get('me/previous-operators', [MeController::class, 'previousOperators'])->name('me.operators');

            Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payments.index');
            Route::post('payment-methods/setup', [PaymentMethodController::class, 'setup'])->name('payments.setup');
            Route::post('payment-methods/complete', [PaymentMethodController::class, 'complete'])->name('payments.complete');
            Route::post('payment-methods/{id}/default', [PaymentMethodController::class, 'makeDefault'])->name('payments.default');
            Route::delete('payment-methods/{id}', [PaymentMethodController::class, 'destroy'])->name('payments.destroy');

            Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
            Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store')->middleware('throttle:20,1');
            Route::get('bookings/{reference}', [BookingController::class, 'show'])->name('bookings.show');
            Route::get('bookings/{reference}/cancellation-fee', [BookingController::class, 'cancellationFee'])->name('bookings.fee');
            Route::post('bookings/{reference}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
            Route::post('bookings/{reference}/reconfirm', [BookingController::class, 'reconfirm'])->name('bookings.reconfirm');
            Route::post('bookings/{reference}/accept-quote', [BookingController::class, 'acceptQuote'])->name('bookings.accept');
            Route::post('bookings/{reference}/rate', [BookingController::class, 'rate'])->name('bookings.rate');
            Route::post('bookings/{reference}/share', [BookingController::class, 'share'])->name('bookings.share');
            Route::get('bookings/{reference}/messages', [BookingController::class, 'messages'])->name('bookings.messages');
            Route::post('bookings/{reference}/messages', [BookingController::class, 'sendMessage'])->name('bookings.messages.send')->middleware('throttle:30,1');
            Route::post('bookings/{reference}/call', [BookingController::class, 'call'])->name('bookings.call')->middleware('throttle:5,1');
        });

        // Crew app
        Route::middleware('abilities:crew')->prefix('crew')->name('crew.')->group(function () {
            Route::get('me', [CrewController::class, 'me'])->name('me');
            Route::post('status', [CrewController::class, 'status'])->name('status');
            Route::post('location', [CrewController::class, 'location'])->name('location')->middleware('throttle:240,1');
            Route::get('feed', [CrewController::class, 'feed'])->name('feed');
            Route::post('offers/{id}/accept', [CrewController::class, 'acceptOffer'])->name('offers.accept');
            Route::post('offers/{id}/decline', [CrewController::class, 'declineOffer'])->name('offers.decline');
            Route::post('assignments/{id}/respond', [CrewController::class, 'respond'])->name('assignments.respond');
            Route::get('jobs/{id}', [CrewController::class, 'job'])->name('jobs.show');
            Route::post('jobs/{id}/action', [CrewController::class, 'action'])->name('jobs.action');
            Route::post('jobs/{id}/verify-pin', [CrewController::class, 'verifyPin'])->name('jobs.pin')->middleware('throttle:10,1');
            Route::get('jobs/{id}/messages', [CrewController::class, 'messages'])->name('jobs.messages');
            Route::post('jobs/{id}/messages', [CrewController::class, 'sendMessage'])->name('jobs.messages.send');
            Route::post('jobs/{id}/call', [CrewController::class, 'call'])->name('jobs.call')->middleware('throttle:5,1');
            Route::post('jobs/{id}/rate', [CrewController::class, 'rate'])->name('jobs.rate');
            Route::get('history', [CrewController::class, 'history'])->name('history');
            Route::get('documents', [CrewController::class, 'documents'])->name('documents');
            Route::post('documents', [CrewController::class, 'uploadDocument'])->name('documents.store');
            Route::get('roster', [CrewController::class, 'roster'])->name('roster');
            Route::post('roster', [CrewController::class, 'requestShift'])->name('roster.store');
        });
    });
});
