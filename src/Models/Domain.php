<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Upsoftware\Svarium\Traits\UsesConnection;

class Domain extends Model
{
    use UsesConnection;

    protected $table = 'domains';

    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
        'locale',
        'theme',
        'status',
        'redirect_to_primary',
        'force_https',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'status' => 'boolean',
        'redirect_to_primary' => 'boolean',
        'force_https' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function getTable(): string
    {
        $table = $this->preferredTableName();
        $fallback = $table === 'tenant_domains' ? 'domains' : 'tenant_domains';

        try {
            if (Schema::hasTable($table)) {
                return $table;
            }

            if (Schema::hasTable($fallback)) {
                return $fallback;
            }
        } catch (Throwable) {
            return $table;
        }

        return $table;
    }

    protected function preferredTableName(): string
    {
        return (bool) config('upsoftware.tenancy.enabled', config('tenancy.enabled', false))
            ? 'tenant_domains'
            : 'domains';
    }
}
