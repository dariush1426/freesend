<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->foreignId('user_subscription_id')->nullable()->constrained('user_subscriptions')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('gateway', 40)->default('zibal');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 8)->default('IRR');
            $table->string('status', 40)->default('pending');
            $table->string('description', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['plan_id', 'status']);
            $table->index(['gateway', 'status']);
        });

        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('subscription_orders')->cascadeOnDelete();
            $table->string('gateway', 40)->default('zibal');
            $table->string('status', 40)->default('pending');
            $table->unsignedBigInteger('amount');
            $table->string('track_id', 80)->nullable()->unique();
            $table->integer('gateway_result')->nullable();
            $table->integer('gateway_status')->nullable();
            $table->boolean('callback_success')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('request_response')->nullable();
            $table->json('callback_payload')->nullable();
            $table->json('verify_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['gateway', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscription_orders');
    }
};
