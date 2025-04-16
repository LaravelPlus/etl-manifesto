<?php

namespace Laravelplus\EtlManifesto\Services;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use stdClass;

class DataExporter
{
    /**
     * Export data based on output configuration
     */
    public function export(Collection $data, array $config): array
    {
        $format = $config['format'] ?? 'csv';
        $path = $config['path'] ?? 'exports/data.csv';

        return match ($format) {
            'csv' => $this->exportToCsv($data, $path, $config),
            'json' => $this->exportToJson($data, $path),
            default => throw new InvalidArgumentException("Unsupported export format: {$format}")
        };
    }

    /**
     * Export data to CSV
     */
    protected function exportToCsv(Collection $data, string $path, array $config): array
    {
        $delimiter = $config['delimiter'] ?? ',';
        $encoding = $config['encoding'] ?? 'UTF-8';
        $includeHeader = $config['header'] ?? true;

        // Create directory if it doesn't exist
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open file for writing: {$path}");
        }

        try {
            // Set encoding if specified
            if ($encoding !== 'UTF-8') {
                stream_filter_append($handle, "convert.iconv.UTF-8.{$encoding}");
            }

            // Write header if requested
            if ($includeHeader && $data->isNotEmpty()) {
                $firstRow = $data->first();
                $headers = $this->getRowKeys($firstRow);
                fputcsv($handle, $headers, $delimiter, '"', '\\');
            }

            // Write data rows
            foreach ($data as $row) {
                $rowData = $this->getRowValues($row);
                fputcsv($handle, $rowData, $delimiter, '"', '\\');
            }

            return [
                'success' => true,
                'path' => $path,
                'format' => 'csv',
                'row_count' => $data->count(),
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Get keys from a row (for headers)
     */
    protected function getRowKeys($row): array
    {
        if (is_array($row)) {
            return array_keys($row);
        }
        if ($row instanceof stdClass) {
            return array_keys(get_object_vars($row));
        }
        if (method_exists($row, 'toArray')) {
            return array_keys($row->toArray());
        }
        throw new \InvalidArgumentException('Unsupported row type');
    }

    /**
     * Get values from a row
     */
    protected function getRowValues($row): array
    {
        if (is_array($row)) {
            return array_values($row);
        }
        if ($row instanceof stdClass) {
            return array_values(get_object_vars($row));
        }
        if (method_exists($row, 'toArray')) {
            return array_values($row->toArray());
        }
        throw new \InvalidArgumentException('Unsupported row type');
    }

    /**
     * Export data to JSON
     */
    protected function exportToJson(Collection $data, string $path): array
    {
        $json = $data->toJson(JSON_PRETTY_PRINT);

        // Create directory if it doesn't exist
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException("Failed to write JSON file: {$path}");
        }

        return [
            'success' => true,
            'path' => $path,
            'format' => 'json',
            'row_count' => $data->count(),
        ];
    }
}
