<?php

namespace Upsoftware\Svarium\Traits;

use Hashids\Hashids;
use Illuminate\Database\Eloquent\Builder;

trait HasHash {
    public function getModelAttribute(): ?string
    {
        $path = explode('\\', get_class($this));
        return array_pop($path);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $parts = explode('\\', get_class($this));
        $model = end($parts); // bez błędu

        $record = $this->where($this->primaryKey, self::hashToId($value, $model))->first();

        if (!$record) {
            if (request()->expectsJson()) {
                abort(response()->json([
                    'status' => 'error',
                    'message' => 'Not found recource',
                    'code' => '404'
                ], 404));
            }

            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(static::class, [$value]);
        }

        return $record;
    }

    public function scopeByHash(Builder $query, string $hash): Builder
    {
        return $query->where($this->getTable().'.'.$this->getKeyName(), self::hashToId($hash, $this->model));
    }

    public function getHash($id) {
        $salt = $this->getSalt();
        $hashids = new Hashids($salt, 64);
        return $hashids->encode($id);
    }

    public function getHashAttribute() {
        return $this->getHash($this->{$this->primaryKey});
    }

    public static function byHash($hash, $get = true)
    {
        $item = self::query()->byHash($hash);
        if ($get) {
            return $item->first();
        } else {
            return $item;
        }
    }

    public function shouldHashPersist(): bool
    {
        return property_exists($this, 'shouldHashPersist')
            ? $this->shouldHashPersist
            : false;
    }

    public static function hashToId(string $hash, ?string $model = null): ?int
    {
        $salt = (new static)->getSalt();
        $hashids = new Hashids($salt, 64);
        $decoded = $hashids->decode($hash);
        return $decoded[0] ?? null;
    }

    protected function getSalt(): string
    {
        return config('app.key') . $this->getModelAttribute();
    }
}
