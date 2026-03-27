<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class ApiAuthTenantSelectController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $user = $request->user();

        if (! is_object($user) || ! method_exists($user, 'currentAccessToken')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unauthenticated.'),
            ], 401);
        }

        $tenant = $this->resolveTenant((string) $data['tenant_id']);
        if (! is_object($tenant) || ! method_exists($tenant, 'getKey')) {
            throw ValidationException::withMessages([
                'tenant_id' => [__('Selected tenant is invalid.')],
            ]);
        }

        $tokenId = $this->resolveCurrentTokenId($request, $user);
        if ($tokenId === null) {
            return response()->json([
                'status' => 'error',
                'message' => __('Access token is missing.'),
            ], 422);
        }

        $connection = function_exists('central_connection') ? central_connection() : null;

        if (! \Schema::connection((string) $connection)->hasColumn('personal_access_tokens', 'tenant_id')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Column tenant_id is missing in personal_access_tokens table.'),
            ], 422);
        }

        $tokenModel = new PersonalAccessToken;
        if (is_string($connection) && trim($connection) !== '' && method_exists($tokenModel, 'setConnection')) {
            $tokenModel->setConnection($connection);
        }

        $updated = $tokenModel->newQuery()
            ->whereKey($tokenId)
            ->update(['tenant_id' => $tenant->getKey()]);

        if ($updated < 1) {
            return response()->json([
                'status' => 'error',
                'message' => __('Access token could not be updated.'),
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'active_tenant_id' => (string) $tenant->getKey(),
            'tenant' => $this->serializeTenant($tenant),
        ], 200);
    }

    protected function resolveCurrentTokenId(Request $request, mixed $user): ?string
    {
        if (is_object($user) && method_exists($user, 'currentAccessToken')) {
            $token = $user->currentAccessToken();
            if (is_object($token) && method_exists($token, 'getKey')) {
                $id = trim((string) $token->getKey());
                if ($id !== '') {
                    return $id;
                }
            }
        }

        $bearer = trim((string) $request->bearerToken());
        if ($bearer === '') {
            return null;
        }

        $id = trim((string) Str::before($bearer, '|'));

        return $id !== '' ? $id : null;
    }

    /**
     * @return array{tenant_id:string}
     */
    protected function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => ['required', 'string', 'max:191'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array{tenant_id:string} $data */
        $data = $validator->validated();

        return $data;
    }

    protected function resolveTenant(string $tenantId): mixed
    {
        $tenantClass = get_model('tenant');
        if (! is_string($tenantClass) || ! class_exists($tenantClass)) {
            return null;
        }

        $connection = function_exists('central_connection') ? central_connection() : null;

        try {
            $tenantModel = new $tenantClass;
            if (is_string($connection) && trim($connection) !== '' && method_exists($tenantModel, 'setConnection')) {
                $tenantModel->setConnection($connection);
            }

            $query = $tenantModel->newQuery();
            $query = $this->applyTenantEnvironmentScope($query, $tenantModel, is_string($connection) ? $connection : null);

            if (ctype_digit($tenantId)) {
                return $query->find($tenantId);
            }

            return $query->byHash($tenantId)->first();
        } catch (Throwable) {
            return null;
        }
    }

    protected function serializeTenant(mixed $tenant): array
    {
        $connection = function_exists('central_connection') ? central_connection() : null;
        $profilePayload = $this->resolveTenantProfilePayload($tenant, is_string($connection) ? $connection : null);

        $hash = data_get($tenant, 'hash');
        if ((! is_string($hash) || trim($hash) === '') && method_exists($tenant, 'getHash')) {
            $hash = (string) $tenant->getHash($tenant->getKey());
        }

        return [
            'id' => (string) $tenant->getKey(),
            'hash' => is_string($hash) ? trim($hash) : null,
            'short_name' => (string) (
                data_get($tenant, 'short_name')
                ?? data_get($profilePayload, 'short_name')
                ?? ''
            ),
            'name' => (string) (
                data_get($tenant, 'name')
                ?? data_get($profilePayload, 'name')
                ?? ''
            ),
            'sio' => data_get($tenant, 'sio') ?? data_get($profilePayload, 'sio'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveTenantProfilePayload(mixed $tenant, ?string $connection = null): array
    {
        if (! is_object($tenant) || ! method_exists($tenant, 'profile')) {
            return [];
        }

        if (! (bool) config('upsoftware.tenancy.profile.enabled', true)) {
            return [];
        }

        if (! $this->tenantProfileTableExists($connection)) {
            return [];
        }

        try {
            $profile = $tenant->profile;
            $payload = is_object($profile) ? $profile->getAttribute('payload') : null;

            return is_array($payload) ? $payload : [];
        } catch (Throwable) {
            return [];
        }
    }

    protected function tenantProfileTableExists(?string $connection = null): bool
    {
        $table = trim((string) config('upsoftware.tenancy.profile.table', 'tenant_profiles'));
        if ($table === '') {
            $table = 'tenant_profiles';
        }

        try {
            if (is_string($connection) && trim($connection) !== '') {
                return \Schema::connection($connection)->hasTable($table);
            }

            return \Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    protected function applyTenantEnvironmentScope(mixed $query, mixed $tenantModel, ?string $connection = null): mixed
    {
        $appEnv = $this->resolveAppEnvironment();
        if ($appEnv === null) {
            return $query;
        }

        $table = is_object($tenantModel) && method_exists($tenantModel, 'getTable')
            ? (string) $tenantModel->getTable()
            : 'tenants';

        try {
            $hasEnvColumn = is_string($connection) && trim($connection) !== ''
                ? \Schema::connection($connection)->hasColumn($table, 'env')
                : \Schema::hasColumn($table, 'env');

            if (! $hasEnvColumn || ! is_object($query) || ! method_exists($query, 'where')) {
                return $query;
            }

            return $query->where($table.'.env', $appEnv);
        } catch (Throwable) {
            return $query;
        }
    }

    protected function resolveAppEnvironment(): ?string
    {
        $env = trim((string) env('APP_ENV', app()->environment()));

        return $env !== '' ? $env : null;
    }
}
