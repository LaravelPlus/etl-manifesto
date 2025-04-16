<?php

namespace Laravelplus\EtlManifesto\Services;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class DataTransformer
{
    /**
     * Apply transformations to the data
     */
    public function transform(Collection $data, array $transformations, array $casts = []): Collection
    {
        return $data->map(function ($item) use ($transformations, $casts) {
            $transformed = (array) $item;

            // Apply transformations
            foreach ($transformations as $field => $transform) {
                if (isset($transformed[$field])) {
                    $transformed[$field] = $this->applyTransform($transformed[$field], $transform);
                }
            }

            // Apply type casting
            foreach ($casts as $field => $type) {
                if (isset($transformed[$field])) {
                    $transformed[$field] = $this->applyCast($transformed[$field], $type);
                }
            }

            return $transformed;
        });
    }

    /**
     * Apply a single transformation
     */
    protected function applyTransform($value, string $transform)
    {
        return match ($transform) {
            'lower' => strtolower($value),
            'upper' => strtoupper($value),
            'trim' => trim($value),
            default => throw new InvalidArgumentException("Unsupported transform: {$transform}")
        };
    }

    /**
     * Apply type casting
     */
    protected function applyCast($value, string $type)
    {
        return match ($type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'boolean' => (bool) $value,
            default => throw new InvalidArgumentException("Unsupported cast type: {$type}")
        };
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
