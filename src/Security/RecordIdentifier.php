<?php

namespace Upsoftware\Svarium\Security;

use Illuminate\Support\Facades\Crypt;

class RecordIdentifier
{
    public static function encode(string $modelClass, int|string $id): string
    {
        return Crypt::encryptString($modelClass.'|'.$id);
    }

    public static function decode(string $value): array
    {
        $decrypted = Crypt::decryptString($value);

        [$modelClass, $id] = explode('|', $decrypted);

        return [$modelClass, $id];
    }
}
