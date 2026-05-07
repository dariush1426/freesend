<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_plans')
            ->where('slug', 'free')
            ->update([
                'allow_folders' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('subscription_plans')
            ->where('slug', 'free')
            ->update([
                'allow_folders' => false,
                'updated_at' => now(),
            ]);
    }
};
