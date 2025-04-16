<?php

namespace Laravelplus\EtlManifesto\Tests;

use Laravelplus\EtlManifesto\EtlManifesto;
use Laravelplus\EtlManifesto\Services\ManifestParser;
use Laravelplus\EtlManifesto\Services\QueryBuilder;
use Laravelplus\EtlManifesto\Services\DataTransformer;
use Laravelplus\EtlManifesto\Services\DataExporter;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Facade;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connectors\ConnectionFactory;
use PHPUnit\Framework\TestCase;

class EtlProcessTest extends TestCase
{
    protected $app;
    protected $manifestPath;
    protected $invalidManifestPath;
    protected $capsule;

    protected function setUp(): void
    {
        parent::setUp();
        
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
        
        // Register database service
        $this->app->singleton('db.factory', function() {
            return new ConnectionFactory($this->app);
        });
        
        $this->app->singleton('db', function() {
            return $this->capsule->getDatabaseManager();
        });
        
        // Set facade root
        Facade::setFacadeApplication($this->app);
        
        // Set up test database
        $this->setupTestDatabase();
        
        // Set up manifest files
        $this->setupManifestFiles();
    }

    protected function setupTestDatabase()
    {
        // Create tables
        $this->capsule->schema()->create('users', function($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        $this->capsule->schema()->create('orders', function($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
        });

        // Insert test data
        $this->capsule->table('users')->insert([
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com']
        ]);

        $this->capsule->table('orders')->insert([
            ['user_id' => 1, 'amount' => 100.00],
            ['user_id' => 1, 'amount' => 50.00]
        ]);
    }

    protected function setupManifestFiles()
    {
        $this->manifestPath = '/tmp/test_manifest.yml';
        $this->invalidManifestPath = '/tmp/invalid_manifest.yml';

        // Create valid manifest
        $validManifest = <<<YAML
etl:
  - id: test_export
    name: Test Export
    source:
      entities:
        - users
      mapping:
        - id: users.id
        - name: users.name
        - email: users.email
    output:
      format: csv
      path: /tmp/test_output.csv
YAML;
        file_put_contents($this->manifestPath, $validManifest);

        // Create invalid manifest
        $invalidManifest = <<<YAML
etl:
  - id: test_export
    name: Test Export
    source:
      entities:
        - users
      mapping:
        - id: users.id
        - name: users.name
        - email: users.email
    output:
      format: csv
      path: /invalid/path/test_output.csv
YAML;
        file_put_contents($this->invalidManifestPath, $invalidManifest);
    }

    public function testValidEtlProcess()
    {
        $etl = new EtlManifesto();
        $results = $etl->loadManifest($this->manifestPath)->process();

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('files', $results);
        $this->assertNotEmpty($results['files']);
        $this->assertFileExists($results['files'][0]);
    }

    public function testInvalidEtlProcess()
    {
        $etl = new EtlManifesto();
        $results = $etl->loadManifest($this->invalidManifestPath)->process();

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('errors', $results);
        $this->assertNotEmpty($results['errors']);
    }

    protected function tearDown(): void
    {
        // Clean up manifest files
        if (file_exists($this->manifestPath)) {
            unlink($this->manifestPath);
        }
        if (file_exists($this->invalidManifestPath)) {
            unlink($this->invalidManifestPath);
        }

        // Clean up output files
        if (file_exists('/tmp/test_output.csv')) {
            unlink('/tmp/test_output.csv');
        }

        parent::tearDown();
    }
} 