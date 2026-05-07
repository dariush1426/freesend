<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('storage_folders')->nullOnDelete();
            $table->string('name', 120);
            $table->timestamps();

            $table->index(['owner_id', 'parent_id'], 'storage_folders_owner_parent_idx');
        });

        if (Schema::hasTable('file_storage_access')) {
            Schema::table('file_storage_access', function (Blueprint $table) {
                $table->foreignId('folder_id')->nullable()->after('context')->constrained('storage_folders')->nullOnDelete();
                $table->index(['user_id', 'folder_id'], 'file_storage_access_user_folder_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('file_storage_access')) {
            Schema::table('file_storage_access', function (Blueprint $table) {
                $table->dropConstrainedForeignId('folder_id');
            });
        }

        Schema::dropIfExists('storage_folders');
    }
};
