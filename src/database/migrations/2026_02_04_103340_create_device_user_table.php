<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('device_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();

            $table->foreignId('device_id')->index()
                ->constrained('devices')
                ->cascadeOnDelete();

            $table->index(['user_id','device_id']);

            $table->timestamps();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('reported_as_rogue_at')->nullable()->index();

            $table->string('name')->nullable();
            $table->text('note')->nullable();
            $table->text('admin_note')->nullable();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('device_user');
    }
};
