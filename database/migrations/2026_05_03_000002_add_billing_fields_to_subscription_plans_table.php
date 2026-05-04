<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('price_amount')->nullable()->after('sort_order');
            $table->unsignedInteger('duration_value')->nullable()->after('price_amount');
            $table->string('duration_unit', 16)->nullable()->after('duration_value');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['price_amount', 'duration_value', 'duration_unit']);
        });
    }
};
