<?php

namespace Upsoftware\Svarium\Models;

use App\Models\User as UserBase;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Upsoftware\Svarium\Traits\HasSetting;
use Upsoftware\Svarium\Traits\UseDevices;
use Upsoftware\Svarium\Traits\UsesConnection;

class User extends UserBase {
    use HasApiTokens, HasSetting, UsesConnection, UseDevices;

    public function routeNotificationForSms()
    {
        return $this->phone_number;
    }

    /**
     * Compatibility createToken implementation for Sanctum API auth.
     * This keeps Svarium API working even when App\Models\User does not use HasApiTokens trait.
     *
     * @param  array<int, string>  $abilities
     */
    public function createToken(string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $this->ensureSanctumAvailable();

        $plainTextToken = $this->generatePlainTextToken();

        $tokenModel = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return new NewAccessToken($tokenModel, $tokenModel->getKey().'|'.$plainTextToken);
    }

    public function tokens(): MorphMany
    {
        $this->ensureSanctumAvailable();

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $tokenModel */
        $tokenModel = Sanctum::$personalAccessTokenModel;

        return $this->morphMany($tokenModel, 'tokenable');
    }

    protected function ensureSanctumAvailable(): void
    {
        if (! class_exists(Sanctum::class) || ! class_exists(NewAccessToken::class)) {
            throw new RuntimeException(
                'Sanctum is required for API driver "sanctum". Install package "laravel/sanctum".'
            );
        }
    }

    protected function generatePlainTextToken(): string
    {
        return config('sanctum.token_prefix', '').Str::random(40);
    }
}
