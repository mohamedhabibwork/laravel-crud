<?php

declare(strict_types=1);

namespace Habib\LaravelCrud\Helper;

use Illuminate\Database\Eloquent\Model;

final class Helper
{
    public static function getAuthorizeModel(string|Model $class, string $ability = 'index'): string
    {
        $class = $class instanceof Model ? $class::class : $class;
        $key = str(class_basename($class))->snake()->singular()->toString();

        return self::getAuthorize($key, $ability);
    }

    public static function getAuthorize(string $key, string $ability): string
    {
        return $key.'.'.$ability;
    }
}
