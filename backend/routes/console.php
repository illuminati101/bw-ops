<?php

use App\Services\Finance\InvoiceService;
use App\Services\Finance\PayoutService;
use Illuminate\Support\Facades\Schedule;

Schedule::command('bw:tick')->everyMinute()->withoutOverlapping();
Schedule::command('bw:daily')->dailyAt('06:30');

// Contractor payouts every Monday for the previous week; EFT invoices on the 1st for the previous month.
Schedule::call(fn () => app(PayoutService::class)->generate(now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()))->weeklyOn(1, '05:00');
Schedule::call(fn () => app(InvoiceService::class)->generate(now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()))->monthlyOn(1, '05:30');
