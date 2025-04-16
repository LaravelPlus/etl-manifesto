<?php

namespace Laravelplus\EtlManifesto\Tests\Unit\Services;

use Laravelplus\EtlManifesto\Tests\TestCase;
use Laravelplus\EtlManifesto\Services\DataExporter;
use Illuminate\Support\Collection;

class DataExporterTest extends TestCase
{
    protected $exporter;
    protected $testData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = new DataExporter();
        
        $this->testData = new Collection([
            (object) [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'amount' => 100.50
            ],
            (object) [
                'id' => 2,
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'amount' => 200.75
            ]
        ]);
    }

    public function test_can_export_to_csv()
    {
        $exporter = new DataExporter();
        $data = collect([
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'amount' => 100.5],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'amount' => 200.75]
        ]);

        $config = [
            'format' => 'csv',
            'path' => '/tmp/test_export.csv',
            'delimiter' => ',',
            'header' => true,
            'encoding' => 'UTF-8'
        ];

        $result = $exporter->export($data, $config);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('/tmp/test_export.csv', $result['path']);
        
        $content = file_get_contents('/tmp/test_export.csv');
        $this->assertStringContainsString('id,name,email,amount', $content);
        $this->assertStringContainsString('1,"John Doe",john@example.com,100.5', $content);
        $this->assertStringContainsString('2,"Jane Smith",jane@example.com,200.75', $content);
    }

    public function test_can_export_to_json()
    {
        $config = [
            'format' => 'json',
            'path' => __DIR__ . '/../../fixtures/output/test.json'
        ];

        $result = $this->exporter->export($this->testData, $config);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertFileExists($result['path']);

        $content = json_decode(file_get_contents($result['path']), true);
        $this->assertCount(2, $content);
        $this->assertEquals('John Doe', $content[0]['name']);
    }

    public function test_handles_invalid_format()
    {
        $config = [
            'format' => 'invalid',
            'path' => __DIR__ . '/../../fixtures/output/test.txt'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->exporter->export($this->testData, $config);
    }

    public function test_creates_output_directory()
    {
        $config = [
            'format' => 'csv',
            'path' => __DIR__ . '/../../fixtures/output/new/test.csv'
        ];

        $result = $this->exporter->export($this->testData, $config);

        $this->assertTrue($result['success']);
        $this->assertDirectoryExists(dirname($result['path']));
    }

    public function test_handles_empty_data()
    {
        $config = [
            'format' => 'csv',
            'path' => __DIR__ . '/../../fixtures/output/empty.csv'
        ];

        $result = $this->exporter->export(new Collection(), $config);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['row_count']);
    }
} 