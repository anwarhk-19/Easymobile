<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->text('address')->nullable();
            $table->string('preferred_language', 10)->default('en');
            $table->timestamps();
        });

        Schema::create('merchant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('owner_name');
            $table->string('alternate_phone', 15)->nullable();
            $table->string('gstin', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('owner_name');
            $table->string('phone', 15);
            $table->string('whatsapp', 15)->nullable();
            $table->text('address');
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('maps_url', 500)->nullable();
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->json('open_days')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('pickup_available')->default(true);
            $table->boolean('delivery_available')->default(false);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->string('delivery_notes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_featured')->default(false)->index();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shop_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shop_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });

        Schema::create('merchant_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('technician_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('shop_name')->nullable();
            $table->string('phone', 15);
            $table->string('whatsapp', 15)->nullable();
            $table->text('address');
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('experience_years')->default(0);
            $table->text('certifications')->nullable();
            $table->text('supported_models_note')->nullable();
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->json('open_days')->nullable();
            $table->boolean('pickup_available')->default(false);
            $table->boolean('onsite_available')->default(false);
            $table->unsignedSmallInteger('default_warranty_days')->default(30);
            $table->text('warranty_terms')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('technician_service_areas', function (Blueprint $table) {
            $table->foreignId('technician_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->primary(['technician_profile_id', 'area_id']);
        });

        Schema::create('technician_brands', function (Blueprint $table) {
            $table->foreignId('technician_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->primary(['technician_profile_id', 'brand_id']);
        });

        Schema::create('technician_phone_models', function (Blueprint $table) {
            $table->foreignId('technician_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phone_model_id')->constrained()->cascadeOnDelete();
            $table->primary(['technician_profile_id', 'phone_model_id']);
        });

        Schema::create('technician_repair_categories', function (Blueprint $table) {
            $table->foreignId('technician_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['technician_profile_id', 'repair_category_id'], 'tech_repair_cat_primary');
        });

        Schema::create('technician_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });

        Schema::create('technician_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['technician_agreements', 'technician_documents', 'technician_repair_categories', 'technician_phone_models',
            'technician_brands', 'technician_service_areas', 'technician_profiles', 'merchant_agreements', 'shop_documents',
            'shop_photos', 'shops', 'merchant_profiles', 'customer_profiles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
