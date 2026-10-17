<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code_hash');
            $table->string('purpose', 16)->default('login');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        // Every SMS / email / push / call we send. Lets staff see login codes while SMS isn't configured.
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 8); // sms | email | push | voice
            $table->string('to');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('driver', 16); // twilio | log | expo | mail
            $table->string('status', 16)->default('queued'); // queued | sent | failed | logged
            $table->string('provider_id')->nullable();
            $table->text('error')->nullable();
            $table->boolean('sensitive')->default(false);
            $table->timestamps();
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform', 8); // ios | android | web
            $table->string('app', 8); // client | crew
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('roster_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_profile_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 16)->default('approved'); // requested | approved | rejected
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('on_call_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedTinyInteger('priority')->default(1); // 1 = primary, 2 = backup
            $table->timestamps();
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_profile_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('jobs_count')->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 16)->default('pending'); // pending | paid
            $table->timestamp('paid_at')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20);
            $table->string('position', 24); // driver | protection | both
            $table->unsignedTinyInteger('years_experience')->default(0);
            $table->string('psira_number', 32)->nullable();
            $table->string('psira_grade', 8)->nullable();
            $table->boolean('firearm_competent')->default(false);
            $table->boolean('has_prdp')->default(false);
            $table->text('message')->nullable();
            $table->json('files')->nullable();
            $table->string('status', 16)->default('new'); // new | reviewing | interview | hired | rejected
            $table->text('staff_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->default('contact'); // contact | corporate
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('status', 16)->default('new'); // new | in_progress | closed
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('staff_notes')->nullable();
            $table->timestamps();
        });

        Schema::table('booking_assignments', function (Blueprint $table) {
            $table->foreign('payout_id')->references('id')->on('payouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['enquiries', 'job_applications', 'payouts', 'on_call_shifts', 'roster_shifts', 'device_tokens', 'outbound_messages', 'otp_codes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
