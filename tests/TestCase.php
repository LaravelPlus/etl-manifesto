<?php

namespace Laravelplus\EtlManifesto\Tests;

use Dotenv\Dotenv;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Facade;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\DatabaseManager;
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

        // Register config
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

        // Register database manager
        $this->app->singleton('db', function($app) {
            return new DatabaseManager($app, $this->capsule->getDatabaseManager()->getConnections());
        });

        // Set facade root
        Facade::setFacadeApplication($this->app);

        // Drop existing tables if they exist
        $this->dropTables();
    }

    protected function dropTables(): void
    {
        $tables = ['users', 'orders', 'payments'];
        foreach ($tables as $table) {
            $this->capsule->schema()->dropIfExists($table);
        }
    }

    protected function tearDown(): void
    {
        $this->dropTables();
        parent::tearDown();
    }
} 