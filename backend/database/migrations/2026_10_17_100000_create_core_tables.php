<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('center_lat', 10, 7);
            $table->decimal('center_lng', 10, 7);
            $table->unsignedInteger('radius_km')->default(60);
            $table->json('bounds')->nullable(); // [south, west, north, east] for address search
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_number')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone', 20)->nullable();
            $table->text('billing_address')->nullable();
            $table->boolean('eft_approved')->default(false);
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('booker'); // admin | booker | passenger
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code_name', 40)->nullable();
            $table->string('verification_status', 16)->default('unverified'); // unverified | pending | verified | rejected
            $table->string('id_document_path')->nullable();
            $table->string('selfie_path')->nullable();
            $table->string('id_number_last4', 8)->nullable();
            $table->timestamp('verification_submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable();
            $table->string('trust_level', 16)->default('new'); // new | trusted
            $table->boolean('eft_approved')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->text('staff_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('relationship', 40)->nullable();
            $table->boolean('notify_on_sos')->default(true);
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('applies_to', 16)->default('operator'); // operator | vehicle
            $table->string('category', 24)->default('capability'); // capability | language | training | vehicle
            $table->string('icon')->nullable();
            $table->string('client_label')->nullable();
            $table->text('client_description')->nullable();
            $table->boolean('client_filterable')->default(false);
            $table->decimal('surcharge_amount', 10, 2)->default(0);
            $table->string('surcharge_type', 16)->default('none'); // none | flat | per_hour
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('operator_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->string('call_sign', 24)->nullable()->unique();
            $table->string('employment_type', 16)->default('employee'); // employee | contractor
            $table->string('roles', 64)->default('driver,guard'); // driver,guard
            $table->string('gender', 16)->nullable();
            $table->string('psira_number', 32)->nullable();
            $table->string('psira_grade', 8)->nullable();
            $table->date('hired_on')->nullable();
            $table->string('status', 16)->default('active'); // active | inactive | suspended
            $table->boolean('is_online')->default(false);
            $table->timestamp('online_since')->nullable();
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->float('last_heading')->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->unsignedBigInteger('current_vehicle_id')->nullable();
            $table->decimal('pay_rate_hourly', 10, 2)->default(0);
            $table->decimal('pay_rate_km', 10, 2)->default(0);
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->text('bank_details')->nullable(); // encrypted
            $table->text('staff_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('feature_operator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_profile_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['feature_id', 'operator_profile_id']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('make');
            $table->string('model');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('colour', 32)->nullable();
            $table->string('registration', 16)->unique();
            $table->string('vehicle_class', 24)->default('sedan'); // sedan | suv | van | armoured_sedan | armoured_suv
            $table->unsignedTinyInteger('seats')->default(3);
            $table->boolean('is_armoured')->default(false);
            $table->string('ownership', 16)->default('company'); // company | operator
            $table->foreignId('owner_operator_id')->nullable()->constrained('operator_profiles')->nullOnDelete();
            $table->string('status', 16)->default('active'); // active | maintenance | retired
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('feature_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['feature_id', 'vehicle_id']);
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('applies_to', 16)->default('operator'); // operator | vehicle
            $table->boolean('requires_expiry')->default(true);
            $table->boolean('required_for_all_jobs')->default(false);
            $table->boolean('required_for_driving')->default(false);
            $table->unsignedSmallInteger('reminder_days')->default(30);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // A feature (e.g. "Armed") only counts while the operator holds valid documents of these types.
        Schema::create('document_type_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->unique(['document_type_id', 'feature_id']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable'); // OperatorProfile | Vehicle
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->string('number', 64)->nullable();
            $table->string('file_path')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->string('status', 16)->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('operator_profiles', function (Blueprint $table) {
            $table->foreign('current_vehicle_id')->references('id')->on('vehicles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['documents', 'document_type_feature', 'document_types', 'feature_vehicle', 'vehicles', 'feature_operator',
            'operator_profiles', 'features', 'emergency_contacts', 'client_profiles', 'company_user', 'companies', 'regions', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
