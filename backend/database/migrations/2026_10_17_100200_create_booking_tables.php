<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 16)->default('paystack'); // paystack | manual
            $table->text('authorization_code')->nullable(); // encrypted token
            $table->string('signature')->nullable()->index(); // gateway card fingerprint, to avoid duplicates
            $table->string('email')->nullable();
            $table->string('brand', 24)->nullable();
            $table->string('last4', 4)->nullable();
            $table->string('exp_month', 2)->nullable();
            $table->string('exp_year', 4)->nullable();
            $table->string('bank')->nullable();
            $table->boolean('reusable')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 16)->default('draft'); // draft | sent | paid | overdue | void
            $table->date('due_on')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignId('client_id')->constrained('users');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('region_id')->constrained();
            $table->string('mode', 16)->default('advance'); // advance | on_demand
            $table->string('structure', 16)->default('single'); // single | team | multi_day | recurring
            $table->string('status', 24)->default('pending_approval')->index();
            $table->string('source', 16)->default('web'); // web | app | admin
            // Principal (the person protected / driven)
            $table->string('principal_name')->nullable();
            $table->string('principal_phone', 20)->nullable();
            $table->string('principal_photo_path')->nullable();
            $table->unsignedTinyInteger('passengers')->default(1);
            // Timing
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->json('recurrence')->nullable(); // {days:[1..7], until:date}
            // Places
            $table->string('pickup_address');
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('dropoff_address')->nullable();
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();
            $table->decimal('estimated_km', 8, 2)->default(0);
            // Requirements
            $table->unsignedTinyInteger('guards_count')->default(0);
            $table->unsignedTinyInteger('vehicles_count')->default(1);
            $table->json('feature_ids')->nullable();
            $table->foreignId('preferred_operator_id')->nullable()->constrained('operator_profiles')->nullOnDelete();
            $table->text('client_notes')->nullable();
            $table->text('operator_notes')->nullable();
            $table->text('internal_notes')->nullable();
            // Money
            $table->json('quote_breakdown')->nullable();
            $table->decimal('quoted_total', 12, 2)->default(0);
            $table->decimal('overtime_rate', 10, 2)->default(0);
            $table->decimal('final_total', 12, 2)->nullable();
            $table->decimal('cancellation_fee', 12, 2)->nullable();
            $table->string('payment_method', 16)->default('card'); // card | card_machine | eft
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('payment_status', 16)->default('unpaid'); // unpaid | held | paid | invoiced | failed | waived
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            // Lifecycle
            $table->string('arrival_pin', 8);
            $table->timestamp('pin_verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('quote_sent_at')->nullable();
            $table->timestamp('quote_accepted_at')->nullable();
            $table->timestamp('reconfirm_due_at')->nullable();
            $table->timestamp('reconfirmed_at')->nullable();
            $table->timestamp('reconfirm_alerted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->decimal('actual_km', 8, 2)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('no_show_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('oncall_alerted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('booking_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('address');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedSmallInteger('wait_minutes')->default(0);
            $table->timestamp('arrived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 16)->default('scheduled'); // scheduled | active | done | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extra_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('amount', 10, 2)->default(0);
        });

        Schema::create('booking_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_shift_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('operator_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 16)->default('driver'); // driver | guard | lead
            $table->string('status', 16)->default('assigned'); // assigned | accepted | declined | en_route | arrived | on_job | done | removed
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->decimal('payout_amount', 10, 2)->nullable();
            $table->unsignedBigInteger('payout_id')->nullable();
            $table->timestamps();
        });

        Schema::create('dispatch_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_profile_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('driver');
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->timestamp('offered_at');
            $table->timestamp('expires_at');
            $table->string('status', 16)->default('pending'); // pending | accepted | declined | expired | cancelled
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['operator_profile_id', 'status']);
        });

        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('summary');
            $table->json('data')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->float('heading')->nullable();
            $table->float('speed')->nullable();
            $table->float('accuracy')->nullable();
            $table->timestamp('recorded_at')->index();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_role', 16); // client | operator | staff | system
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sos_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('raised_by_role', 16); // client | operator
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('status', 16)->default('open'); // open | acknowledged | resolved | false_alarm | cancelled
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('last_escalated_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ratee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 24); // client_to_operator | operator_to_client
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->boolean('flagged')->default(false);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16); // verify | hold | release | capture | charge | refund | fee
            $table->string('gateway', 16); // paystack | manual | card_machine | eft
            $table->decimal('amount', 12, 2);
            $table->string('status', 16)->default('pending'); // pending | success | failed
            $table->string('reference')->nullable()->index();
            $table->string('gateway_reference')->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('strikes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('trip_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('label')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['trip_shares', 'strikes', 'payments', 'ratings', 'sos_alerts', 'messages', 'location_pings', 'booking_events',
            'dispatch_offers', 'booking_assignments', 'booking_extras', 'booking_shifts', 'booking_stops', 'bookings', 'invoices', 'payment_methods'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
