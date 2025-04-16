<?php

namespace Laravelplus\EtlManifesto\Tests;

use Dotenv\Dotenv;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Facade;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected $app;
    protected $capsule;

    protected function setUp(): void
    {
        parent::setUp();

        // Load environment variables
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        // Create Laravel application instance
        $this->app = new Application();
        $this->app->singleton('config', function() {
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

        // Set facade root
        Facade::setFacadeApplication($this->app);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
} 