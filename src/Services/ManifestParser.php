<?php

namespace Laravelplus\EtlManifesto\Services;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

class ManifestParser
{
    /**
     * Parse a YAML manifest file
     */
    public function parse(string $path): array
    {
        if (! file_exists($path)) {
            throw new InvalidArgumentException("Manifest file not found: {$path}");
        }

        $content = file_get_contents($path);
        $data = Yaml::parse($content);

        $this->validateManifest($data);

        return $data;
    }

    /**
     * Validate the manifest structure
     */
    protected function validateManifest(array $data): void
    {
        if (! isset($data['etl']) || ! is_array($data['etl'])) {
            throw new InvalidArgumentException('Invalid manifest: missing or invalid "etl" section');
        }

        foreach ($data['etl'] as $job) {
            $this->validateJob($job);
        }
    }

    /**
     * Validate a single ETL job
     */
    protected function validateJob(array $job): void
    {
        $requiredFields = ['id', 'name', 'source', 'output'];
        foreach ($requiredFields as $field) {
            if (! isset($job[$field])) {
                throw new InvalidArgumentException("Missing required field '{$field}' in ETL job");
            }
        }

        $this->validateSource($job['source']);
        $this->validateOutput($job['output']);
    }

    /**
     * Validate the source section
     */
    protected function validateSource(array $source): void
    {
        if (! isset($source['entities']) || ! is_array($source['entities'])) {
            throw new InvalidArgumentException('Invalid source: missing or invalid "entities"');
        }

        if (isset($source['relationships']) && ! is_array($source['relationships'])) {
            throw new InvalidArgumentException('Invalid source: "relationships" must be an array');
        }

        if (isset($source['conditions']) && ! is_array($source['conditions'])) {
            throw new InvalidArgumentException('Invalid source: "conditions" must be an array');
        }

        if (! isset($source['mapping']) || ! is_array($source['mapping'])) {
            throw new InvalidArgumentException('Invalid source: missing or invalid "mapping"');
        }
    }

    /**
     * Validate the output section
     */
    protected function validateOutput(array $output): void
    {
        $requiredFields = ['format', 'path'];
        foreach ($requiredFields as $field) {
            if (! isset($output[$field])) {
                throw new InvalidArgumentException("Missing required field '{$field}' in output section");
            }
        }

        if (! in_array($output['format'], ['csv', 'json'])) {
            throw new InvalidArgumentException('Invalid output format. Must be either "csv" or "json"');
        }
    }
}
