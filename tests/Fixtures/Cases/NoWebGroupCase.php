<?php

namespace ModuHelp\Laravel\Tests\Fixtures\Cases;

use Illuminate\Contracts\Http\Kernel;
use ModuHelp\Laravel\Tests\Fixtures\NoWebGroupKernel;
use ModuHelp\Laravel\Tests\TestCase;

class NoWebGroupCase extends TestCase
{
    protected function resolveApplicationHttpKernel($app)
    {
        $app->singleton(Kernel::class, NoWebGroupKernel::class);
    }
}
