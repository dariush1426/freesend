<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('max_upload_size_mb')->nullable();
            $table->unsignedInteger('max_storage_mb')->nullable();
            $table->json('expire_options')->nullable();
            $table->boolean('allow_public_links')->default(true);
            $table->boolean('allow_password_protection')->default(true);
            $table->boolean('allow_custom_expiry')->default(true);
            $table->boolean('allow_never_expire')->default(false);
            $table->boolean('allow_personal_storage')->default(false);
            $table->boolean('allow_team_features')->default(false);
            $table->unsignedInteger('max_team_members')->nullable();
            $table->boolean('allow_signature_workflow')->default(false);
            $table->boolean('allow_folders')->default(false);
            $table->boolean('allow_ai_features')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['plan_id', 'status']);
        });

        DB::table('subscription_plans')->insert([
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'Default base plan for all users.',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 0,
            'max_upload_size_mb' => null,
            'max_storage_mb' => null,
            'expire_options' => json_encode(['default', '1', '2', '5', '12', '24', 'custom'], JSON_UNESCAPED_UNICODE),
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => true,
            'allow_never_expire' => false,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'max_team_members' => null,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
