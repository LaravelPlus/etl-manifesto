<?php

namespace Laravelplus\EtlManifesto\Tests\Unit;

use Laravelplus\EtlManifesto\Tests\TestCase;
use Laravelplus\EtlManifesto\EtlManifesto;
use Illuminate\Support\Facades\DB;

class EtlManifestoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Drop existing tables
        $this->dropTables();
        
        // Create test tables
        $this->createTestTables();
        
        // Insert test data
        $this->insertTestData();
    }

    protected function dropTables(): void
    {
        DB::statement('DROP TABLE IF EXISTS payments');
        DB::statement('DROP TABLE IF EXISTS orders');
        DB::statement('DROP TABLE IF EXISTS users');
    }

    protected function createTestTables()
    {
        DB::statement('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255),
            email VARCHAR(255),
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            product_id VARCHAR(255),
            quantity INTEGER,
            amount DECIMAL(10,2),
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )');

        DB::statement('CREATE TABLE payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER,
            amount DECIMAL(10,2),
            status VARCHAR(255),
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id)
        )');
    }

    protected function insertTestData()
    {
        // Insert users
        DB::table('users')->insert([
            ['name' => 'John Doe', 'email' => 'john@example.com', 'is_active' => true],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'is_active' => true],
        ]);

        // Insert orders
        DB::table('orders')->insert([
            [
                'user_id' => 1,
                'product_id' => 'P001',
                'quantity' => 2,
                'amount' => 100.00,
                'created_at' => now()->subMonth()->startOfMonth()->addDays(5)
            ],
            [
                'user_id' => 1,
                'product_id' => 'P002',
                'quantity' => 1,
                'amount' => 50.00,
                'created_at' => now()->subMonth()->startOfMonth()->addDays(10)
            ],
        ]);

        // Insert payments
        DB::table('payments')->insert([
            ['order_id' => 1, 'amount' => 100.00, 'status' => 'completed'],
            ['order_id' => 2, 'amount' => 50.00, 'status' => 'completed'],
        ]);
    }

    public function test_can_load_manifest()
    {
        $etl = new EtlManifesto();
        $manifest = $etl->loadManifest(__DIR__ . '/../fixtures/etl.yml');
        
        $this->assertInstanceOf(EtlManifesto::class, $manifest);
        $this->assertIsArray($etl->getManifest());
    }

    public function test_can_process_manifest()
    {
        $etl = new EtlManifesto();
        $results = $etl->loadManifest(__DIR__ . '/../fixtures/etl.yml')->process();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('files', $results);
        $this->assertArrayHasKey('errors', $results);
    }

    public function test_generates_correct_output()
    {
        $manifestPath = '/tmp/test_manifest.yml';
        $manifestContent = <<<YAML
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
        file_put_contents($manifestPath, $manifestContent);

        $manifesto = new EtlManifesto();
        $manifesto->loadManifest($manifestPath);
        $results = $manifesto->process();

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('files', $results);
        $this->assertNotEmpty($results['files']);
        $this->assertFileExists($results['files'][0]);

        unlink($manifestPath);
        unlink($results['files'][0]);
    }

    public function test_handles_errors_gracefully()
    {
        $manifestPath = '/tmp/invalid_manifest.yml';
        $manifestContent = <<<YAML
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
        file_put_contents($manifestPath, $manifestContent);

        $manifesto = new EtlManifesto();
        $manifesto->loadManifest($manifestPath);
        $results = $manifesto->process();

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('errors', $results);
        $this->assertNotEmpty($results['errors']);

        unlink($manifestPath);
    }

    protected function tearDown(): void
    {
        $this->dropTables();
        parent::tearDown();
    }
} 