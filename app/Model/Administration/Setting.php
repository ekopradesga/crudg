<?php

namespace App\Model\Administration;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public static function set($key, $value) {
        $model = static::query()->create(['key' => $key, 'content' => $value]);
        return $model ? $value : false;
    }

    public static function get($key) {
        $model = static::query()->where('key', $key)->first();
        return $model ? $model->content : false;
    }
}
