<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Setting extends Model {
    protected $fillable = [
        'key',
        'user_id',
        'model_type',
        'model_id',
        'value'
    ];

    protected $casts = [
        'value' => 'array',
        'user_id' => 'integer',
    ];

    /**
     * Pobierz ustawienie dla danego modelu.
     *
     * @param string $modelType
     * @param int $modelId
     * @param string $key
     * @return mixed|null
     */
    public static function getSetting($modelType, $modelId, $key, $connection = null)
    {
        $modelType = svarium_model_type($modelType);

        if (! static::settingsTableExists($connection)) {
            return null;
        }

        $query = $connection ? self::on($connection) : self::query();

        $setting = $query->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->first();

        return $setting ? ($setting->value[$key] ?? null) : null;
    }

    /**
     * Ustaw wartość ustawienia dla danego modelu.
     *
     * @param string $modelType
     * @param int $modelId
     * @param string $key
     * @param mixed $value
     * @return string|bool
     */
    public static function setSetting($modelType, $modelId, $settingKey, $value = null, $connection = null)
    {
        $modelType = svarium_model_type($modelType);

        if (is_array($settingKey)) {
            foreach ($settingKey as $key => $value) {
                self::setSetting($modelType, $modelId, $key, $value, $connection);
            }
            return null;
        }

        if (! static::settingsTableExists($connection)) {
            return null;
        }

        $query = $connection ? self::on($connection) : new self;

        $setting = $query->firstOrCreate(
            ['model_type' => $modelType, 'model_id' => $modelId],
            ['value' => []]
        );

        $setting->value = array_merge($setting->value, [$settingKey => $value]);
        $setting->save();

        return $setting;
    }

    /**
     * Usuń ustawienie dla danego modelu.
     *
     * @param string $modelType
     * @param int $modelId
     * @param string $key
     * @return bool
     */
    public static function removeSetting($modelType, $modelId, $settingKey, $connection = null)
    {
        $modelType = svarium_model_type($modelType);

        if (is_array($settingKey)) {
            foreach ($settingKey as $key) {
                self::removeSetting($modelType, $modelId, $key);
            }
            return true;
        }

        if (! static::settingsTableExists($connection)) {
            return false;
        }

        $setting = self::where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->first();

        if (!$setting || !isset($setting->value[$settingKey])) {
            return false;
        }

        $value = $setting->values;
        unset($value[$settingKey]);

        $setting->value = $value;
        $setting->save();

        return true;
    }

    public static function getSettingGlobal(
        string $key,
        $default = null,
        $connection = null,
        int|string|null $userId = null,
        bool $fallbackToGlobal = true
    ) {
        if (! static::settingsTableExists($connection)) {
            return $default;
        }

        if ($userId !== null) {
            $value = static::resolveStoredSettingValue(
                static::settingGlobalQuery($key, $connection, $userId)->orderBy('id')->get()
            );

            if ($value !== null || ! $fallbackToGlobal) {
                return $value ?? $default;
            }
        }

        $value = static::resolveStoredSettingValue(
            static::settingGlobalQuery($key, $connection, null)->orderBy('id')->get()
        );

        return $value ?? $default;
    }

    public static function setSettingGlobal(
        string $key,
        $value,
        bool $force = false,
        int|string|null $userId = null
    ): void {
        if (! static::settingsTableExists(null)) {
            return;
        }

        $records = static::settingGlobalQuery($key, null, $userId)
            ->orderBy('id')
            ->get();

        $existingValue = static::resolveStoredSettingValue($records);
        if (! $force && is_array($value) && is_array($existingValue)) {
            $value = array_replace($existingValue, $value);
        }

        $config = $records->last();
        if ($config instanceof static) {
            $config->update(['value' => $value]);
        } else {
            $config = static::create([
                'key' => $key,
                'user_id' => $userId,
                'value' => $value,
            ]);
        }

        static::deleteDuplicateGlobalSettings($key, $userId, (int) $config->getKey());
    }

    public static function removeSettingGlobal(string $key, int|string|null $userId = null): void {
        if (! static::settingsTableExists(null)) {
            return;
        }

        static::settingGlobalQuery($key, null, $userId)->delete();
    }

    public static function getSettingGlobalForUser(
        string $key,
        $default = null,
        $connection = null,
        int|string|null $userId = null,
        bool $fallbackToGlobal = true
    ) {
        $resolvedUserId = $userId ?? auth()->id();

        return static::getSettingGlobal($key, $default, $connection, $resolvedUserId, $fallbackToGlobal);
    }

    public static function setSettingGlobalForUser(
        string $key,
        $value,
        bool $force = false,
        int|string|null $userId = null
    ): void {
        $resolvedUserId = $userId ?? auth()->id();

        static::setSettingGlobal($key, $value, $force, $resolvedUserId);
    }

    public static function removeSettingGlobalForUser(string $key, int|string|null $userId = null): void {
        $resolvedUserId = $userId ?? auth()->id();

        static::removeSettingGlobal($key, $resolvedUserId);
    }

    protected static function settingGlobalQuery(
        string $key,
        ?string $connection = null,
        int|string|null $userId = null
    ) {
        $query = $connection
            ? static::on($connection)
            : static::on(static::resolveConnectionName());
        $query->where('key', $key);

        if ($userId === null) {
            $query->whereNull('user_id');
        } else {
            $query->where('user_id', $userId);
        }

        return $query;
    }

    protected static function settingsTableExists(?string $connection = null): bool
    {
        try {
            $schema = $connection !== null && $connection !== ''
                ? Schema::connection($connection)
                : Schema::connection(static::resolveConnectionName());

            return $schema->hasTable((new static)->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    protected static function resolveConnectionName(): ?string
    {
        if (function_exists('central_connection')) {
            $central = trim((string) central_connection());

            if ($central !== '') {
                return $central;
            }
        }

        $configuredCentral = trim((string) config('upsoftware.tenancy.database.central_connection', ''));
        if ($configuredCentral !== '') {
            return $configuredCentral;
        }

        $default = config('database.default');
        if (is_string($default) && trim($default) !== '') {
            return trim($default);
        }

        $modelConnection = (new static)->getConnectionName();
        if (is_string($modelConnection) && trim($modelConnection) !== '') {
            return trim($modelConnection);
        }

        return null;
    }

    public function getConnectionName(): ?string
    {
        $forced = parent::getConnectionName();
        if (is_string($forced) && trim($forced) !== '') {
            return trim($forced);
        }

        return static::resolveConnectionName();
    }

    protected static function resolveStoredSettingValue($records)
    {
        if (! method_exists($records, 'all')) {
            return null;
        }

        $resolved = null;

        foreach ($records as $record) {
            if (! $record instanceof static) {
                continue;
            }

            $currentValue = $record->value;

            if (is_array($resolved) && is_array($currentValue)) {
                $resolved = array_replace($resolved, $currentValue);

                continue;
            }

            if ($currentValue !== null) {
                $resolved = $currentValue;
            }
        }

        return $resolved;
    }

    protected static function deleteDuplicateGlobalSettings(
        string $key,
        int|string|null $userId = null,
        ?int $keepId = null
    ): void {
        $query = static::settingGlobalQuery($key, null, $userId);

        if ($keepId !== null) {
            $query->whereKeyNot($keepId);
        }

        $query->delete();
    }
}
