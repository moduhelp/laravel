<?php

namespace ModuHelp\Laravel\Facades;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool enabled()
 * @method static string origin()
 * @method static int ttl()
 * @method static void identifyUsing(Closure $fn)
 * @method static void showWhen(Closure $fn)
 * @method static void nonceUsing(Closure $fn)
 * @method static void skip(?Request $request = null)
 * @method static Authenticatable|null user()
 * @method static bool shouldShow(Request $request)
 * @method static string|null token(?Authenticatable $user = null)
 * @method static string|null nonce(Request $request)
 * @method static bool renderedOn(Request $request)
 * @method static bool identifiedOn(Request $request)
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
