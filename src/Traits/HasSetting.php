<?php

namespace Upsoftware\Svarium\Traits;

use Upsoftware\Svarium\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

trait HasSetting
{
    /**
     * Usuwanie ustawień powiązanych z tym modelem po jego usunięciu.
     */
    public static function bootHasSettings()
    {
        static::deleted(function (Model $model) {
            Setting::where('model_type', svarium_model_type($model))
                ->where('model_id', $model->id)
                ->delete();
        });
    }

    public function scopeWhereSetting(Builder $query, string $key, string $operatorOrValue, mixed $value = null): Builder {
        if (func_num_args() === 3) {
            return $query->whereHas('settings', function (Builder $q) use ($key, $operatorOrValue) {
                $q->where('model_type', svarium_model_type(static::class))
                  ->whereRaw(
                      "JSON_UNQUOTE(JSON_EXTRACT(`values`, ?)) = ?",
                      ['$.'.$key, $operatorOrValue]
                  );
            });
        }

        return $query->whereHas('settings', function (Builder $q) use ($key, $operatorOrValue, $value) {
            $q->where('model_type', svarium_model_type(static::class))
              ->whereRaw(
                  "JSON_UNQUOTE(JSON_EXTRACT(`values`, ?)) {$operatorOrValue} ?",
                  ['$.'.$key, $value]
              );
        });
    }

    /**
     * Relacja z ustawieniami.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function settings()
    {
        return $this->hasOne(Setting::class, 'model_id')->where('model_type', svarium_model_type($this));
    }

    /**
     * Pobierz ustawienie dla danego modelu.
     *
     * @param string $key
     * @return mixed
     */
    public function getSetting($key, $default = null, $connection = null)
    {
        return Setting::getSetting(svarium_model_type($this), $this->id, $key, $connection) ?? $default;
    }

    /**
     * Ustaw wartość ustawienia dla danego modelu.
     *
     * @param string $key
     * @param mixed $value
     * @return string|bool
     */
    public function setSetting($key, $value = null, $connection = null)
    {
        return Setting::setSetting(svarium_model_type($this), $this->id, $key, $value, $connection);
    }

    /**
     * Usuń ustawienie dla danego modelu.
     *
     * @param string $key
     * @return bool
     */
    public function removeSetting($key, $connection = null)
    {
        return Setting::removeSetting(svarium_model_type($this), $this->id, $key, $connection);
    }
}
