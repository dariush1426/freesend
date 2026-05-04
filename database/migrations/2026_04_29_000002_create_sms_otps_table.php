<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_otps', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 20);
            $table->string('purpose', 40);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['mobile', 'purpose', 'expires_at']);
            $table->index(['mobile', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_otps');
    }
};
