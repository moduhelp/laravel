<?php

namespace ModuHelp\Laravel\Tests\Fixtures;

use Orchestra\Testbench\Foundation\Http\Kernel;

/** A kernel that defines no "web" middleware group. */
class NoWebGroupKernel extends Kernel
{
    public function setMiddlewareGroups(array $groups)
    {
        return parent::setMiddlewareGroups([]);
    }
}
