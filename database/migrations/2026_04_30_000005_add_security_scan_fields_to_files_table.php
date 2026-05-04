<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->string('security_scan_status', 24)->default('clean')->after('status');
            $table->string('security_scan_message', 255)->nullable()->after('security_scan_status');
            $table->timestamp('security_scanned_at')->nullable()->after('security_scan_message');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn([
                'security_scan_status',
                'security_scan_message',
                'security_scanned_at',
            ]);
        });
    }
};
