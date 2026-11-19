<?php

namespace ModuHelp\Laravel\Tests\Fixtures\Cases;

use ModuHelp\Laravel\Tests\TestCase;

class NoKeyCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('moduhelp.widget_key', null);
    }
}
