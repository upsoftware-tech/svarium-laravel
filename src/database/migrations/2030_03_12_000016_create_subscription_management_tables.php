<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_modules')) {
            Schema::create('subscription_modules', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('status')->default(true);
                $table->decimal('base_price', 12, 2)->default(0);
                $table->char('currency', 3)->default('PLN');
                $table->string('billing_period', 20)->default('month');
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();

                $table->index(['status', 'sort'], 'subscription_modules_status_sort_index');
                $table->index('billing_period', 'subscription_modules_billing_period_index');
            });
        }

        if (! Schema::hasTable('subscription_limit_tiers')) {
            Schema::create('subscription_limit_tiers', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('min_value')->nullable();
                $table->unsignedInteger('max_value')->nullable();
                $table->boolean('is_unlimited')->default(false);
                $table->decimal('price_delta', 12, 2)->default(0);
                $table->char('currency', 3)->default('PLN');
                $table->boolean('status')->default(true);
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();

                $table->index(['status', 'sort'], 'subscription_limit_tiers_status_sort_index');
            });
        }

        if (! Schema::hasTable('tenant_subscriptions')) {
            Schema::create('tenant_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->string('tenant_id')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('customer_email')->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('billing_period', 20)->default('month');
                $table->char('currency', 3)->default('PLN');
                $table->decimal('total_price', 12, 2)->default(0);
                $table->text('notes')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index('tenant_id', 'tenant_subscriptions_tenant_id_index');
                $table->index('status', 'tenant_subscriptions_status_index');
                $table->index(['starts_at', 'ends_at'], 'tenant_subscriptions_period_index');
            });
        }

        if (! Schema::hasTable('tenant_subscription_items')) {
            Schema::create('tenant_subscription_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_subscription_id')
                    ->constrained('tenant_subscriptions')
                    ->cascadeOnDelete();
                $table->foreignId('subscription_module_id')
                    ->constrained('subscription_modules')
                    ->cascadeOnDelete();
                $table->foreignId('subscription_limit_tier_id')
                    ->nullable()
                    ->constrained('subscription_limit_tiers')
                    ->nullOnDelete();
                $table->unsignedInteger('module_limit')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('total_price', 12, 2)->default(0);
                $table->boolean('status')->default(true);
                $table->unsignedInteger('sort')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['tenant_subscription_id', 'status'], 'tenant_subscription_items_sub_status_index');
                $table->index('subscription_module_id', 'tenant_subscription_items_module_index');
                $table->index('subscription_limit_tier_id', 'tenant_subscription_items_limit_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscription_items');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('subscription_limit_tiers');
        Schema::dropIfExists('subscription_modules');
    }
};

