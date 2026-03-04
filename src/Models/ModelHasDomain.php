<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Upsoftware\Svarium\Traits\UsesConnection;

class ModelHasDomain extends Model
{
    use UsesConnection;

    protected $table = 'model_has_domains';

    protected $fillable = [
        'domain_id',
        'tenant_domain_id',
        'tenant_id',
        'model_type',
        'model_id',
    ];

    public function getTable(): string
    {
        $table = parent::getTable();

        try {
            if (Schema::hasTable($table)) {
                return $table;
            }

            if (Schema::hasTable('model_has_domain_tenants')) {
                return 'model_has_domain_tenants';
            }
        } catch (Throwable) {
            return $table;
        }

        return $table;
    }
}
