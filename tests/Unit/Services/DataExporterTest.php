<?php

namespace Laravelplus\EtlManifesto\Tests\Unit\Services;

use Laravelplus\EtlManifesto\Tests\TestCase;
use Laravelplus\EtlManifesto\Services\DataExporter;
use Illuminate\Support\Collection;

class DataExporterTest extends TestCase
{
    protected $exporter;
    protected $testOutputDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->exporter = new DataExporter();
        $this->testOutputDir = __DIR__ . '/../../fixtures/output';
        
        // Ensure output directory exists and is writable
        if (!is_dir($this->testOutputDir)) {
            mkdir($this->testOutputDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (is_dir($this->testOutputDir)) {
            array_map('unlink', glob("$this->testOutputDir/*.*"));
        }
        parent::tearDown();
    }

    public function test_can_export_to_csv()
    {
        $data = new Collection([
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

        $config = [
            'format' => 'csv',
            'path' => $this->testOutputDir . '/test.csv',
            'delimiter' => ',',
            'header' => true,
        ];

        $result = $this->exporter->export($data, $config);

        $this->assertTrue($result['success']);
        $this->assertFileExists($result['path']);
        $this->assertEquals('csv', $result['format']);
        $this->assertEquals(2, $result['row_count']);
    }

    public function test_can_export_to_json()
    {
        $data = new Collection([
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

        $config = [
            'format' => 'json',
            'path' => $this->testOutputDir . '/test.json',
        ];

        $result = $this->exporter->export($data, $config);

        $this->assertTrue($result['success']);
        $this->assertFileExists($result['path']);
        $this->assertEquals('json', $result['format']);
    }

    public function test_handles_invalid_format()
    {
        $data = new Collection([
            ['id' => 1, 'name' => 'John'],
        ]);

        $config = [
            'format' => 'invalid',
            'path' => $this->testOutputDir . '/test.txt',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->exporter->export($data, $config);
    }

    public function test_creates_output_directory()
    {
        $data = new Collection([
            ['id' => 1, 'name' => 'John'],
        ]);

        $config = [
            'format' => 'csv',
            'path' => $this->testOutputDir . '/subdir/test.csv',
        ];

        $result = $this->exporter->export($data, $config);

        $this->assertTrue($result['success']);
        $this->assertFileExists($result['path']);
    }

    public function test_handles_empty_data()
    {
        $data = new Collection([]);

        $config = [
            'format' => 'csv',
            'path' => $this->testOutputDir . '/empty.csv',
            'header' => true,
        ];

        $result = $this->exporter->export($data, $config);

        $this->assertTrue($result['success']);
        $this->assertFileExists($result['path']);
        $this->assertEquals(0, $result['row_count']);
    }
} 