<?php

use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PaymentReturnController;
use App\Http\Controllers\Site\TripShareController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/services', [PageController::class, 'services'])->name('services.index');
Route::get('/services/{slug}', [PageController::class, 'service'])->name('services.show');
Route::get('/legal/{slug}', [PageController::class, 'legal'])->name('legal')->where('slug', 'terms|privacy|cancellation');
Route::post('/enquiries/{type}', [PageController::class, 'enquiry'])->name('enquiries.store')->where('type', 'contact|corporate')->middleware('throttle:6,1');
Route::post('/careers/apply', [PageController::class, 'apply'])->name('careers.apply')->middleware('throttle:4,1');

Route::get('/preview/page/{page}', [PageController::class, 'preview'])->name('preview.page')->middleware('signed');
Route::get('/t/{token}', [TripShareController::class, 'show'])->name('trip.share');
Route::get('/t/{token}/data', [TripShareController::class, 'data'])->name('trip.share.data')->middleware('throttle:60,1');

Route::get('/payments/callback', [PaymentReturnController::class, 'callback'])->name('payments.callback');
Route::get('/payments/return', [PaymentReturnController::class, 'done'])->name('payments.return');
Route::post('/payments/webhook', [PaymentReturnController::class, 'webhook'])->name('payments.webhook');

// Client portal (Livewire) and remaining CMS pages are registered in routes/portal.php
require __DIR__.'/portal.php';

Route::get('/{slug}', [PageController::class, 'page'])->name('page')->where('slug', 'about|faq|careers|corporate|contact');

// Staff-only endpoints (session auth from the admin panel)
Route::middleware(['web', 'auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('files/client/{profile}/{kind}', [\App\Http\Controllers\Staff\StaffFilesController::class, 'client'])->name('files.client');
    Route::get('files/application/{application}/{index}', [\App\Http\Controllers\Staff\StaffFilesController::class, 'application'])->name('files.application');
    Route::get('invoices/{invoice}', [\App\Http\Controllers\Staff\StaffFilesController::class, 'invoice'])->name('invoice');
    Route::get('export/{type}', [\App\Http\Controllers\Staff\ExportController::class, 'export'])->name('export')->where('type', 'bookings|payments|payouts|clients|operators');
    Route::get('control-room/data', [\App\Http\Controllers\Staff\ControlRoomController::class, 'data'])->name('control-room.data');
});
