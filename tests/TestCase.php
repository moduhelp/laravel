<?php

namespace ModuHelp\Laravel\Tests;

use ModuHelp\Laravel\ModuHelpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ModuHelpServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('moduhelp.widget_key', 'wk_test');
        $app['config']->set('moduhelp.identity_secret', 'mis_test_secret');
    }
}
