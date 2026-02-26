<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model {
    protected $fillable = [
        'key',
        'model_type',
        'model_id',
        'value'
    ];

    protected $casts = [
        'value' => 'array',
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
        if (is_array($settingKey)) {
            foreach ($settingKey as $key => $value) {
                self::setSetting($modelType, $modelId, $key, $value, $connection);
            }
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
        if (is_array($settingKey)) {
            foreach ($settingKey as $key) {
                self::removeSetting($modelType, $modelId, $key);
            }
            return true;
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

    public static function getSettingGlobal(string $key, $default = null, $connection = null) {
        $query = $connection ? self::on($connection) : self::on(env('DB_CONNECTION'));
        return $query->where('key', $key)->value('value') ?? $default;
    }

    public static function setSettingGlobal(string $key, $value, $force = false): void {
        $config = static::where('key', $key)->first();
        if ($config) {
            $value = $value + $config->value;
            $config->update(['value' => $value]);
        } else {
            static::create(['key' => $key, 'value' => $value]);
        }
    }

    public static function removeSettingGlobal(string $key): void {
        static::where('key', $key)->delete();
    }
}
