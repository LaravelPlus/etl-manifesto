<?php

namespace Laravelplus\EtlManifesto\Tests\Unit\Services;

use Illuminate\Support\Facades\DB;
use Laravelplus\EtlManifesto\Services\QueryBuilder;
use Laravelplus\EtlManifesto\Tests\TestCase;

class QueryBuilderTest extends TestCase
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
    }

    protected function insertTestData()
    {
        DB::table('users')->insert([
            ['name' => 'John Doe', 'email' => 'john@example.com', 'is_active' => true],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'is_active' => true],
        ]);

        DB::table('orders')->insert([
            [
                'user_id' => 1,
                'product_id' => 'P001',
                'quantity' => 2,
                'amount' => 100.00,
                'created_at' => now()->subMonth()->startOfMonth()->addDays(5),
            ],
            [
                'user_id' => 1,
                'product_id' => 'P002',
                'quantity' => 1,
                'amount' => 50.00,
                'created_at' => now()->subMonth()->startOfMonth()->addDays(10),
            ],
        ]);
    }

    public function test_can_build_basic_query()
    {
        $builder = new QueryBuilder;
        $config = [
            'entities' => ['users'],
            'mapping' => [
                ['id' => 'users.id'],
                ['name' => 'users.name'],
                ['email' => 'users.email'],
            ],
        ];

        $query = $builder->build($config);
        $results = $query->get();

        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]->name);
    }

    public function test_can_build_query_with_conditions()
    {
        $builder = new QueryBuilder;
        $config = [
            'entities' => ['users'],
            'conditions' => [
                ['users.is_active' => true],
            ],
            'mapping' => [
                ['id' => 'users.id'],
                ['name' => 'users.name'],
            ],
        ];

        $query = $builder->build($config);
        $results = $query->get();

        $this->assertCount(2, $results);
    }

    public function test_can_build_query_with_aggregations()
    {
        $builder = new QueryBuilder;
        $config = [
            'entities' => ['users', 'orders'],
            'relationships' => ['users hasMany orders'],
            'mapping' => [
                ['id' => 'users.id'],
                ['name' => 'users.name'],
                ['total_orders' => [
                    'function' => 'count',
                    'column' => 'orders.id',
                ]],
            ],
            'group_by' => ['users.id', 'users.name'],
        ];

        $query = $builder->build($config);
        $results = $query->get();

        $this->assertCount(2, $results);
        $this->assertEquals(2, $results[0]->total_orders);
    }

    public function test_can_build_query_with_concatenation()
    {
        $builder = new QueryBuilder;
        $config = [
            'entities' => [
                'users' => [
                    'table' => 'users',
                    'fields' => ['id', 'name', 'email'],
                ],
            ],
            'mapping' => [
                [
                    'source' => 'users.id',
                    'target' => 'id',
                ],
                [
                    'source' => 'users.name, " <", users.email, ">"',
                    'target' => 'display_name',
                    'aggregate' => 'concat',
                ],
            ],
        ];

        $query = $builder->build($config);
        $results = $query->get();

        $this->assertCount(2, $results);
        $this->assertEquals('John Doe" <"john@example.com">"', $results[0]->display_name);
    }

    protected function tearDown(): void
    {
        $this->dropTables();
        parent::tearDown();
    }
}
