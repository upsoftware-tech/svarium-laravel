<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ResetController extends Controller
{
    public function init() {
        set_title('Reset');
        return inertia('Auth/Reset');
    }

    public function reset(Request $request) {
        $request->validate([
            'email' => ['required', 'string', 'email:rfc,dns'],
        ]);

        $tenant_id = tenant()?->id;
        $userModel = get_model('user');
        $user = $userModel::query()->where('email', $request->email)->first();
        $has_role = false;

        if ($user) {
            $modelType = svarium_model_type($user);
            $roleModel = get_model('model_has_role');
            $queryRole = $roleModel::query()
                ->where('model_id', $user->id)
                ->where('model_type', $modelType)
                ->where('status', 1);

            if (svarium_tenancy_column_mode()) {
                $table = (new $roleModel())->getTable();
                $connection = (new $roleModel())->getConnectionName();
                $hasTenantColumn = is_string($connection) && $connection !== ''
                    ? Schema::connection($connection)->hasColumn($table, 'tenant_id')
                    : Schema::hasColumn($table, 'tenant_id');

                if ($hasTenantColumn && $tenant_id !== null && $tenant_id !== '') {
                    $tenantRoleExists = (clone $queryRole)
                        ->where('tenant_id', $tenant_id)
                        ->exists();

                    if ($tenantRoleExists) {
                        $has_role = true;
                    } else {
                        $globalRoleExists = (clone $queryRole)
                            ->where(function ($builder): void {
                                $builder->whereNull('tenant_id')->orWhere('tenant_id', '');
                            })
                            ->exists();

                        if ($globalRoleExists) {
                            $mapModel = get_model('model_has_tenant');
                            $has_role = $mapModel::query()
                                ->where('model_id', $user->id)
                                ->where('model_type', $modelType)
                                ->where('tenant_id', $tenant_id)
                                ->exists();
                        } else {
                            $has_role = false;
                        }
                    }
                } else {
                    $has_role = $queryRole->exists();
                }
            } else {
                $has_role = $queryRole->exists();
            }
        }

        $session = sha1(md5(time()));
        if ($user && $has_role) {
            $authSession = get_model('user_auth')::create([
                'type' => 'reset',
                'user_id' => $user->id,
            ]);
            $authSession->sendEmail('reset');
            $session = $authSession->hash;
        }

        return redirect()->to(route_panel('verification', ['type' => 'reset', 'userAuth' => $session]))->with(['alert_info' => ['text' => __('If an account associated with this email address exists, you will receive a message with a verification code'), 'duration' => 0]]);
    }
}
