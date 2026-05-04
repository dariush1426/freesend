<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_sends', function (Blueprint $table): void {
            $table->string('public_token', 80)->nullable()->unique()->after('downloaded_at');
            $table->boolean('public_link_enabled')->default(false)->after('public_token');
            $table->timestamp('public_link_expires_at')->nullable()->after('public_link_enabled');
            $table->unsignedInteger('public_max_downloads')->nullable()->after('public_link_expires_at');
            $table->unsignedInteger('public_download_count')->default(0)->after('public_max_downloads');
            $table->timestamp('public_last_downloaded_at')->nullable()->after('public_download_count');
        });

        Schema::create('file_send_public_downloads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('file_send_id')->constrained('file_sends')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['file_send_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_send_public_downloads');

        Schema::table('file_sends', function (Blueprint $table): void {
            $table->dropColumn([
                'public_token',
                'public_link_enabled',
                'public_link_expires_at',
                'public_max_downloads',
                'public_download_count',
                'public_last_downloaded_at',
            ]);
        });
    }
};
