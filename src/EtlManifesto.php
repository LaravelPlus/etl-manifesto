<?php

namespace Laravelplus\EtlManifesto;

use Illuminate\Support\Collection;
use Laravelplus\EtlManifesto\Contracts\EtlManifestInterface;
use Laravelplus\EtlManifesto\Services\DataExporter;
use Laravelplus\EtlManifesto\Services\DataTransformer;
use Laravelplus\EtlManifesto\Services\ManifestParser;
use Laravelplus\EtlManifesto\Services\QueryBuilder;

class EtlManifesto implements EtlManifestInterface
{
    protected array $manifest = [];

    protected ?ManifestParser $parser;

    protected ?QueryBuilder $queryBuilder;

    protected ?DataTransformer $transformer;

    protected ?DataExporter $exporter;

    public function __construct(
        ?ManifestParser $parser = null,
        ?QueryBuilder $queryBuilder = null,
        ?DataTransformer $transformer = null,
        ?DataExporter $exporter = null
    ) {
        $this->parser = $parser ?? new ManifestParser;
        $this->queryBuilder = $queryBuilder ?? new QueryBuilder;
        $this->transformer = $transformer ?? new DataTransformer;
        $this->exporter = $exporter ?? new DataExporter;
    }

    /**
     * Load and parse the manifest file
     */
    public function loadManifest(string $path): self
    {
        $this->manifest = $this->parser->parse($path);

        return $this;
    }

    /**
     * Process the loaded manifest
     */
    public function process(): array
    {
        $results = [
            'files' => [],
            'errors' => [],
        ];

        try {
            foreach ($this->manifest['etl'] as $job) {
                $data = $this->processJob($job);
                $transformedData = $this->applyTransforms($data, $job['transform'] ?? []);
                $exportResult = $this->exportResults($transformedData, $job['output']);

                if (isset($exportResult['path'])) {
                    $results['files'][] = $exportResult['path'];
                }
            }
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    protected function processJob(array $job): Collection
    {
        $query = $this->queryBuilder->build($job['source']);

        return $query->get();
    }

    protected function applyTransforms(Collection $data, array $transforms): Collection
    {
        if (empty($transforms)) {
            return $data;
        }

        return $data->map(function ($item) use ($transforms) {
            foreach ($transforms as $field => $transform) {
                if (isset($item->$field)) {
                    $item->$field = $this->transformer->transform($item->$field, $transform);
                }
            }

            return $item;
        });
    }

    protected function exportResults(Collection $data, array $output): array
    {
        return $this->exporter->export($data, $output);
    }

    /**
     * Get the current manifest data
     */
    public function getManifest(): array
    {
        return $this->manifest;
    }
}
