<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type')->nullable();
            $table->string('extension', 32)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('storage_path');
            $table->string('checksum', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->index(['owner_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('file_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->index(['receiver_id', 'created_at']);
            $table->index(['sender_id', 'receiver_id']);
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('file_sends');
        Schema::dropIfExists('files');
    }
};
