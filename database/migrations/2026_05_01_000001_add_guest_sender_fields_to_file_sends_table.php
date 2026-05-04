<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_sends', function (Blueprint $table): void {
            $table->dropForeign(['sender_id']);
        });

        Schema::table('file_sends', function (Blueprint $table): void {
            $table->unsignedBigInteger('sender_id')->nullable()->change();
            $table->string('sender_name', 120)->nullable()->after('sender_id');
            $table->string('sender_contact', 120)->nullable()->after('sender_name');
            $table->foreign('sender_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('file_sends', function (Blueprint $table): void {
            $table->dropForeign(['sender_id']);
        });

        Schema::table('file_sends', function (Blueprint $table): void {
            $table->dropColumn(['sender_name', 'sender_contact']);
            $table->unsignedBigInteger('sender_id')->nullable(false)->change();
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
