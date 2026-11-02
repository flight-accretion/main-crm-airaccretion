<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait UsesUuidPrimaryKey
{
    public function getIncrementing()
    {
        return false;
    }

    public function getKeyType()
    {
        return 'string';
    }

    protected static function bootUsesUuidPrimaryKey(): void
    {
        static::creating(function ($model): void {
            if (!$model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
