<?php

namespace Upsoftware\Svarium\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class EncryptedOrPlainText implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! is_string($value)) {
            return (string) $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Backward compatibility for legacy plaintext values.
            return $value;
        }
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $stringValue = (string) $value;

        // Keep already encrypted payload untouched.
        try {
            Crypt::decryptString($stringValue);

            return $stringValue;
        } catch (DecryptException) {
            return Crypt::encryptString($stringValue);
        }
    }
}
