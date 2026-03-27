<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

        $token = $user->currentAccessToken();
        if (! is_object($token) || ! method_exists($token, 'getKey')) {
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

        $tokenModel->newQuery()
            ->whereKey($token->getKey())
            ->update(['tenant_id' => $tenant->getKey()]);

        return response()->json([
            'status' => 'ok',
            'tenant' => $this->serializeTenant($tenant),
        ], 200);
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
        $profilePayload = [];
        if (method_exists($tenant, 'profile')) {
            $profile = $tenant->profile;
            $payload = is_object($profile) ? $profile->getAttribute('payload') : null;
            if (is_array($payload)) {
                $profilePayload = $payload;
            }
        }

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
}
