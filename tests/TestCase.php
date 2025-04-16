<?php

namespace Laravelplus\EtlManifesto\Tests;

use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Facades\Facade;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create Laravel application instance
        $this->app = new \Illuminate\Foundation\Application;
        $this->app->singleton('config', function () {
            return new Repository([
                'database' => [
                    'default' => 'sqlite',
                    'connections' => [
                        'sqlite' => [
                            'driver' => 'sqlite',
                            'database' => ':memory:',
                            'prefix' => '',
                        ],
                    ],
                ],
            ]);
        });

        // Set up database
        $this->capsule = new Capsule;
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        // Register database service
        $this->app->singleton('db.factory', function () {
            return new ConnectionFactory($this->app);
        });

        $this->app->singleton('db', function () {
            return $this->capsule->getDatabaseManager();
        });

        // Set facade root
        Facade::setFacadeApplication($this->app);
    }

    protected function getPackageProviders($app)
    {
        return [
            \Laravelplus\EtlManifesto\EtlManifestoServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'EtlManifesto' => \Laravelplus\EtlManifesto\Facades\EtlManifesto::class,
        ];
    }
}
