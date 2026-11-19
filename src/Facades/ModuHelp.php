<?php

namespace ModuHelp\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool enabled()
 * @method static string origin()
 * @method static int ttl()
 *
 * @see \ModuHelp\Laravel\ModuHelp
 */
class ModuHelp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ModuHelp\Laravel\ModuHelp::class;
    }
}
