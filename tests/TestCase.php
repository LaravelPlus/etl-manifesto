<?php

namespace Laravelplus\EtlManifesto\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Laravelplus\EtlManifesto\EtlManifestoServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            EtlManifestoServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
} 