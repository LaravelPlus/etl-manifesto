<?php

namespace Laravelplus\EtlManifesto\Services;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class DataTransformer
{
    /**
     * Apply transformations to the data
     */
    public function transform($data, array $config)
    {
        if ($data instanceof Collection) {
            $data = $data->first();
        }

        if (is_array($data)) {
            $data = $data[0] ?? null;
        }

        if ($data === null) {
            return null;
        }

        $type = $config['type'] ?? null;

        return match ($type) {
            'lower' => strtolower((string) $data),
            'upper' => strtoupper((string) $data),
            'date' => $this->formatDate($data, $config['format'] ?? 'Y-m-d'),
            'number' => $this->formatNumber($data, $config['decimals'] ?? 2),
            'boolean' => $this->formatBoolean($data),
            default => $data
        };
    }

    protected function formatDate($date, string $format): string
    {
        if (is_numeric($date)) {
            return date($format, $date);
        }

        return date($format, strtotime($date));
    }

    protected function formatNumber($number, int $decimals): string
    {
        return number_format((float) $number, $decimals);
    }

    protected function formatBoolean($value): string
    {
        return $value ? '1' : '0';
    }

    /**
     * Apply post-group transformations
     */
    public function applyPostGroupTransforms(Collection $data, array $transforms): Collection
    {
        return $data->map(function ($item) use ($transforms) {
            $transformed = (array) $item;

            foreach ($transforms as $field => $transform) {
                if (isset($transform['function']) && isset($transform['args'])) {
                    $transformed[$field] = $this->applyPostGroupTransform($transformed, $transform);
                }
            }

            return $transformed;
        });
    }

    /**
     * Apply a single post-group transform
     */
    protected function applyPostGroupTransform(array $data, array $transform)
    {
        $function = $transform['function'];
        $args = array_map(fn ($arg) => $data[$arg] ?? $arg, $transform['args']);

        return match ($function) {
            'divide' => $args[0] / $args[1],
            'multiply' => $args[0] * $args[1],
            'add' => $args[0] + $args[1],
            'subtract' => $args[0] - $args[1],
            default => throw new InvalidArgumentException("Unsupported post-group transform: {$function}")
        };
    }
}
