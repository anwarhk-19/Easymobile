<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('technician_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('phone_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_model_text')->nullable();
            $table->foreignId('repair_category_id')->constrained()->restrictOnDelete();
            $table->text('issue_description');
            $table->string('service_mode', 20)->default('walk_in');
            $table->foreignId('preferred_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->text('address')->nullable();
            $table->string('preferred_time')->nullable();
            $table->boolean('data_access_consent')->default(false);
            $table->text('imei_encrypted')->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->text('rejection_reason')->nullable();
            $table->json('test_checklist')->nullable();
            $table->text('technician_notes')->nullable();
            $table->decimal('final_amount', 12, 2)->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_status', 20)->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('diagnosed_at')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('testing_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('handover_confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['technician_profile_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('repair_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('repair_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('findings');
            $table->timestamps();
        });

        Schema::create('repair_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->text('diagnosis');
            $table->decimal('parts_cost', 10, 2)->default(0);
            $table->decimal('labour_cost', 10, 2)->default(0);
            $table->string('parts_type', 30);
            $table->decimal('total', 10, 2);
            $table->unsignedSmallInteger('estimated_hours');
            $table->unsignedSmallInteger('warranty_days')->default(0);
            $table->text('warranty_exclusions')->nullable();
            $table->text('change_reason')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('responded_at')->nullable();
            $table->text('customer_note')->nullable();
            $table->timestamps();
            $table->unique(['repair_request_id', 'version']);
        });

        Schema::create('repair_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('parts_type', 30);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('repair_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('repair_warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('warranty_code', 30)->unique();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->text('terms')->nullable();
            $table->text('exclusions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['repair_warranties', 'repair_status_histories', 'repair_parts', 'repair_quotations', 'repair_diagnoses', 'repair_images', 'repair_requests'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
