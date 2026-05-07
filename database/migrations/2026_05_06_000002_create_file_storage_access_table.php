<?php

use App\Models\FileStorageAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_storage_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 24)->default(FileStorageAccess::ROLE_VIEWER);
            $table->string('context', 24)->default(FileStorageAccess::CONTEXT_OWNED);
            $table->timestamps();

            $table->unique(['file_id', 'user_id'], 'file_storage_access_unique');
            $table->index(['user_id', 'context'], 'file_storage_access_user_context_idx');
        });

        $now = now();

        DB::table('files')
            ->where('is_personal_storage', true)
            ->orderBy('id')
            ->chunkById(200, function ($files) use ($now): void {
                $rows = [];

                foreach ($files as $file) {
                    $rows[] = [
                        'file_id' => $file->id,
                        'user_id' => $file->owner_id,
                        'role' => FileStorageAccess::ROLE_OWNER,
                        'context' => FileStorageAccess::CONTEXT_OWNED,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('file_storage_access')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_storage_access');
    }
};
