<?php

namespace Laravelplus\EtlManifesto\Tests\Unit\Services;

use Laravelplus\EtlManifesto\Services\DataTransformer;
use Laravelplus\EtlManifesto\Tests\TestCase;

class DataTransformerTest extends TestCase
{
    protected $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new DataTransformer;
    }

    public function test_can_transform_to_lowercase()
    {
        $data = 'TEST@EXAMPLE.COM';
        $result = $this->transformer->transform($data, ['type' => 'lower']);
        $this->assertEquals('test@example.com', $result);
    }

    public function test_can_transform_to_uppercase()
    {
        $data = 'test@example.com';
        $result = $this->transformer->transform($data, ['type' => 'upper']);
        $this->assertEquals('TEST@EXAMPLE.COM', $result);
    }

    public function test_can_format_date()
    {
        $data = '2024-01-01';
        $result = $this->transformer->transform($data, ['type' => 'date', 'format' => 'Y-m-d']);
        $this->assertEquals('2024-01-01', $result);
    }

    public function test_can_handle_null_values()
    {
        $data = null;
        $result = $this->transformer->transform($data, ['type' => 'lower']);
        $this->assertNull($result);
    }

    public function test_can_handle_invalid_transformations()
    {
        $data = 'test@example.com';
        $result = $this->transformer->transform($data, ['type' => 'invalid']);
        $this->assertEquals('test@example.com', $result);
    }

    public function test_can_handle_numeric_values()
    {
        $data = 100.50;
        $result = $this->transformer->transform($data, ['type' => 'number', 'decimals' => 2]);
        $this->assertEquals('100.50', $result);
    }

    public function test_can_handle_boolean_values()
    {
        $data = true;
        $result = $this->transformer->transform($data, ['type' => 'boolean']);
        $this->assertEquals('1', $result);
    }
}
