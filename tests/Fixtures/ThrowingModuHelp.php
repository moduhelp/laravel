<?php

namespace ModuHelp\Laravel\Tests\Fixtures;

use Illuminate\Http\Request;
use ModuHelp\Laravel\ModuHelp;
use RuntimeException;

class ThrowingModuHelp extends ModuHelp
{
    public function render(Request $request): ?string
    {
        throw new RuntimeException('render blew up');
    }
}
