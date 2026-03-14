<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('translation_keysets')) {
            Schema::create('translation_keysets', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('scope', 32)->default('global');
                $table->string('scope_key')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('source_locale', 12)->default('en');
                $table->boolean('status')->default(true);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['scope', 'scope_key'], 'translation_keysets_scope_index');
                $table->index(['status', 'code'], 'translation_keysets_status_code_index');
            });
        }

        if (! Schema::hasTable('translation_keys')) {
            Schema::create('translation_keys', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('translation_keyset_id')
                    ->constrained('translation_keysets')
                    ->cascadeOnDelete();
                $table->string('key');
                $table->string('type', 32)->default('text');
                $table->string('category')->nullable();
                $table->text('context')->nullable();
                $table->text('description')->nullable();
                $table->json('placeholders')->nullable();
                $table->unsignedInteger('max_length')->nullable();
                $table->boolean('status')->default(true);
                $table->unsignedInteger('sort')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['translation_keyset_id', 'key'], 'translation_keys_keyset_key_unique');
                $table->index(['status', 'type'], 'translation_keys_status_type_index');
                $table->index(['category', 'sort'], 'translation_keys_category_sort_index');
            });
        }

        if (! Schema::hasTable('translation_values')) {
            Schema::create('translation_values', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('translation_key_id')
                    ->constrained('translation_keys')
                    ->cascadeOnDelete();
                $table->string('locale', 12);
                $table->longText('value')->nullable();
                $table->string('status', 24)->default('missing');
                $table->boolean('is_machine')->default(false);
                $table->string('updated_by')->nullable();
                $table->string('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['translation_key_id', 'locale'], 'translation_values_key_locale_unique');
                $table->index(['locale', 'status'], 'translation_values_locale_status_index');
            });
        }

        if (! Schema::hasTable('translation_revisions')) {
            Schema::create('translation_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('translation_key_id')
                    ->constrained('translation_keys')
                    ->cascadeOnDelete();
                $table->foreignId('translation_value_id')
                    ->nullable()
                    ->constrained('translation_values')
                    ->nullOnDelete();
                $table->string('locale', 12);
                $table->string('change_type', 24)->default('update');
                $table->longText('old_value')->nullable();
                $table->longText('new_value')->nullable();
                $table->string('old_status', 24)->nullable();
                $table->string('new_status', 24)->nullable();
                $table->string('changed_by')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['translation_key_id', 'locale'], 'translation_revisions_key_locale_index');
                $table->index(['change_type', 'created_at'], 'translation_revisions_type_created_index');
            });
        }

        if (! Schema::hasTable('translation_orders')) {
            Schema::create('translation_orders', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status', 24)->default('open');
                $table->string('priority', 24)->default('normal');
                $table->string('requested_by')->nullable();
                $table->string('assigned_to')->nullable();
                $table->string('source_locale', 12)->nullable();
                $table->json('target_locales')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['status', 'priority'], 'translation_orders_status_priority_index');
                $table->index(['assigned_to', 'due_at'], 'translation_orders_assignee_due_index');
            });
        }

        if (! Schema::hasTable('translation_order_items')) {
            Schema::create('translation_order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('translation_order_id')
                    ->constrained('translation_orders')
                    ->cascadeOnDelete();
                $table->foreignId('translation_key_id')
                    ->constrained('translation_keys')
                    ->cascadeOnDelete();
                $table->string('locale', 12);
                $table->string('status', 24)->default('open');
                $table->text('note')->nullable();
                $table->string('completed_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['translation_order_id', 'translation_key_id', 'locale'],
                    'translation_order_items_unique'
                );
                $table->index(['status', 'locale'], 'translation_order_items_status_locale_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_order_items');
        Schema::dropIfExists('translation_orders');
        Schema::dropIfExists('translation_revisions');
        Schema::dropIfExists('translation_values');
        Schema::dropIfExists('translation_keys');
        Schema::dropIfExists('translation_keysets');
    }
};

