<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Columns shared by every model that uses the draft → preview → publish workflow. */
    private function publishable(Blueprint $table): void
    {
        $table->json('draft')->nullable();
        $table->boolean('is_published')->default(false);
        $table->timestamp('published_at')->nullable();
        $table->timestamp('publish_at')->nullable()->index(); // scheduled publish of the draft
        $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
    }

    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 32)->default('transport'); // transport | protection | transport_protection
            $table->string('tagline')->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->json('highlights')->nullable();
            $table->string('ideal_for')->nullable();
            $table->boolean('includes_transport')->default(true);
            $table->boolean('includes_protection')->default(false);
            $table->unsignedTinyInteger('default_guards')->default(0);
            $table->unsignedTinyInteger('default_vehicles')->default(1);
            $table->boolean('allows_on_demand')->default(true);
            $table->boolean('allows_advance')->default(true);
            $table->string('structure', 16)->default('single'); // single | team | multi_day
            $table->unsignedInteger('min_notice_minutes')->default(120);
            $table->unsignedSmallInteger('min_duration_minutes')->default(60);
            $table->string('approval_rule', 16)->default('first_booking'); // never | first_booking | always
            $table->boolean('custom_quote')->default(false);
            $table->json('default_feature_ids')->nullable();
            $table->string('icon')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $this->publishable($table);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rate_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->decimal('callout_fee', 10, 2)->default(0);
            $table->decimal('per_km', 10, 2)->default(0);
            $table->decimal('per_hour', 10, 2)->default(0);
            $table->decimal('per_extra_guard_hour', 10, 2)->default(0);
            $table->decimal('minimum_charge', 10, 2)->default(0);
            $table->unsignedSmallInteger('included_km')->default(0);
            $table->unsignedSmallInteger('included_wait_minutes')->default(15);
            $table->decimal('overtime_per_hour', 10, 2)->nullable(); // falls back to per_hour
            $this->publishable($table);
            $table->timestamps();
            $table->unique(['service_id', 'region_id']);
        });

        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('pricing', 16)->default('flat'); // flat | per_hour | percent
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('requires_vehicle')->default(false);
            $table->foreignId('feature_id')->nullable()->constrained()->nullOnDelete(); // selecting it requires this feature
            $table->unsignedInteger('sort')->default(0);
            $this->publishable($table);
            $table->timestamps();
        });

        Schema::create('extra_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extra_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unique(['extra_id', 'service_id']);
        });

        Schema::create('surcharge_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind', 16); // night | weekend | holiday
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->decimal('percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('meta_description')->nullable();
            $table->string('kind', 16)->default('page'); // page | legal
            $table->json('blocks')->nullable();
            $table->longText('body')->nullable(); // markdown for legal pages
            $table->boolean('show_in_footer')->default(false);
            $this->publishable($table);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 40)->default('Booking');
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort')->default(0);
            $this->publishable($table);
            $table->timestamps();
        });

        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->morphs('revisionable');
            $table->json('snapshot');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['revisions', 'faqs', 'pages', 'public_holidays', 'surcharge_rules', 'extra_service', 'extras', 'rate_cards', 'services'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
