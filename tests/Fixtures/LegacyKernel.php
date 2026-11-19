<?php

namespace ModuHelp\Laravel\Tests\Fixtures;

use Illuminate\Routing\Middleware\SubstituteBindings;
use Orchestra\Testbench\Foundation\Http\Kernel;

/** Mimics an app/Http/Kernel.php that declares its groups as a property. */
class LegacyKernel extends Kernel
{
    protected $middlewareGroups = [
        'web' => [SubstituteBindings::class],
    ];

    public function setMiddlewareGroups(array $groups)
    {
        return $this;
    }
}
