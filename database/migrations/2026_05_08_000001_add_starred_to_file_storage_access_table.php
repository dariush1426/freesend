<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('file_storage_access')) {
            return;
        }

        Schema::table('file_storage_access', function (Blueprint $table): void {
            if (! Schema::hasColumn('file_storage_access', 'is_starred')) {
                $table->boolean('is_starred')->default(false)->after('folder_id');
                $table->index(['user_id', 'is_starred'], 'file_storage_access_user_starred_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('file_storage_access') || ! Schema::hasColumn('file_storage_access', 'is_starred')) {
            return;
        }

        Schema::table('file_storage_access', function (Blueprint $table): void {
            $table->dropIndex('file_storage_access_user_starred_idx');
            $table->dropColumn('is_starred');
        });
    }
};
