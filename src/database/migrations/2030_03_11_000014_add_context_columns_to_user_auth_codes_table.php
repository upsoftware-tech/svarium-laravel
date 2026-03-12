<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $table = 'user_auth_codes';

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table): void {
            if (! $this->columnExists('used_at')) {
                $table->dateTime('used_at')->nullable();
            }

            if (! $this->columnExists('sent_ip')) {
                $table->string('sent_ip', 64)->nullable();
            }

            if (! $this->columnExists('sent_user_agent')) {
                $table->text('sent_user_agent')->nullable();
            }

            if (! $this->columnExists('sent_device_type')) {
                $table->string('sent_device_type', 64)->nullable();
            }

            if (! $this->columnExists('sent_platform')) {
                $table->string('sent_platform', 64)->nullable();
            }

            if (! $this->columnExists('sent_platform_ver')) {
                $table->string('sent_platform_ver', 64)->nullable();
            }

            if (! $this->columnExists('sent_browser')) {
                $table->string('sent_browser', 64)->nullable();
            }

            if (! $this->columnExists('sent_browser_ver')) {
                $table->string('sent_browser_ver', 64)->nullable();
            }

            if (! $this->columnExists('used_ip')) {
                $table->string('used_ip', 64)->nullable();
            }

            if (! $this->columnExists('used_user_agent')) {
                $table->text('used_user_agent')->nullable();
            }

            if (! $this->columnExists('used_device_type')) {
                $table->string('used_device_type', 64)->nullable();
            }

            if (! $this->columnExists('used_platform')) {
                $table->string('used_platform', 64)->nullable();
            }

            if (! $this->columnExists('used_platform_ver')) {
                $table->string('used_platform_ver', 64)->nullable();
            }

            if (! $this->columnExists('used_browser')) {
                $table->string('used_browser', 64)->nullable();
            }

            if (! $this->columnExists('used_browser_ver')) {
                $table->string('used_browser_ver', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table): void {
            if ($this->columnExists('used_at')) {
                $table->dropColumn('used_at');
            }

            foreach ([
                'sent_ip',
                'sent_user_agent',
                'sent_device_type',
                'sent_platform',
                'sent_platform_ver',
                'sent_browser',
                'sent_browser_ver',
                'used_ip',
                'used_user_agent',
                'used_device_type',
                'used_platform',
                'used_platform_ver',
                'used_browser',
                'used_browser_ver',
            ] as $column) {
                if ($this->columnExists($column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    protected function columnExists(string $column): bool
    {
        try {
            return Schema::hasColumn($this->table, $column);
        } catch (\Throwable) {
            return false;
        }
    }
};
