<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_auth_codes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->datetime('expired_at');
            $table->foreignId('user_auth_id')->constrained();
            $table->boolean('is_used')->nullable()->default(0);
            $table->string('code')->nullable();
            $table->enum('method', ['app', 'sms', 'email'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_auth_codes');
    }
};
