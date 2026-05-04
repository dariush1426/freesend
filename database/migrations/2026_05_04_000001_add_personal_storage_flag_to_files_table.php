<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->boolean('is_personal_storage')->default(false)->after('owner_id');
            $table->index(['owner_id', 'is_personal_storage', 'status'], 'files_owner_personal_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropIndex('files_owner_personal_status_idx');
            $table->dropColumn('is_personal_storage');
        });
    }
};
