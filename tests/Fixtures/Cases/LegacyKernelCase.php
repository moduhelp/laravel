<?php

namespace ModuHelp\Laravel\Tests\Fixtures\Cases;

use Illuminate\Contracts\Http\Kernel;
use ModuHelp\Laravel\Tests\Fixtures\LegacyKernel;
use ModuHelp\Laravel\Tests\TestCase;

class LegacyKernelCase extends TestCase
{
    protected function resolveApplicationHttpKernel($app)
    {
        $app->singleton(Kernel::class, LegacyKernel::class);
    }
}
