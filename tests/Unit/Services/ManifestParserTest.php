<?php

namespace Laravelplus\EtlManifesto\Tests\Unit\Services;

use Laravelplus\EtlManifesto\Services\ManifestParser;
use Laravelplus\EtlManifesto\Tests\TestCase;

class ManifestParserTest extends TestCase
{
    protected $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ManifestParser;
    }

    public function test_can_parse_valid_manifest()
    {
        $manifest = $this->parser->parse(__DIR__.'/../../fixtures/etl.yml');

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('etl', $manifest);
        $this->assertCount(1, $manifest['etl']);

        $job = $manifest['etl'][0];
        $this->assertEquals('monthly_user_summary', $job['id']);
        $this->assertArrayHasKey('source', $job);
        $this->assertArrayHasKey('output', $job);
    }

    public function test_throws_exception_for_invalid_file()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse('non_existent_file.yml');
    }

    public function test_throws_exception_for_invalid_yaml()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse(__DIR__.'/../../fixtures/invalid.yml');
    }

    public function test_validates_required_fields()
    {
        $manifest = $this->parser->parse(__DIR__.'/../../fixtures/etl.yml');
        $job = $manifest['etl'][0];

        $this->assertArrayHasKey('id', $job);
        $this->assertArrayHasKey('source', $job);
        $this->assertArrayHasKey('output', $job);
    }

    public function test_validates_source_configuration()
    {
        $manifest = $this->parser->parse(__DIR__.'/../../fixtures/etl.yml');
        $source = $manifest['etl'][0]['source'];

        $this->assertArrayHasKey('entities', $source);
        $this->assertArrayHasKey('relationships', $source);
        $this->assertArrayHasKey('mapping', $source);
    }

    public function test_validates_output_configuration()
    {
        $manifest = $this->parser->parse(__DIR__.'/../../fixtures/etl.yml');
        $output = $manifest['etl'][0]['output'];

        $this->assertArrayHasKey('format', $output);
        $this->assertArrayHasKey('path', $output);
    }
}
