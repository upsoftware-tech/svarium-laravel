<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $table = 'system_mailboxes';

    public function up(): void
    {
        if (Schema::hasTable($this->table)) {
            return;
        }

        Schema::create($this->table, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->boolean('is_default')->default(false);
            $table->enum('scope_type', ['global', 'tenant', 'domain', 'panel'])->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();

            $table->enum('driver', ['smtp', 'ses', 'mailgun', 'postmark', 'sendmail', 'log'])->default('smtp');
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('encryption', 32)->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();

            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('reply_to_email')->nullable();
            $table->json('config')->nullable();

            $table->timestamps();

            $table->index(['scope_type', 'scope_id'], 'system_mailboxes_scope_index');
            $table->index(['status', 'is_default'], 'system_mailboxes_status_default_index');
            $table->index('driver', 'system_mailboxes_driver_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
