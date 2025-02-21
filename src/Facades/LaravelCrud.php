<?php

namespace Habib\LaravelCrud\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Habib\LaravelCrud\LaravelCrud
 */
class LaravelCrud extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Habib\LaravelCrud\LaravelCrud::class;
    }
}
