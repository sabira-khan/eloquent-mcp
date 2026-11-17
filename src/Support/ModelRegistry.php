<?php

namespace SabiraKhan\EloquentMcp\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ModelRegistry
{
    private ?array $models = null;

    public function __construct(private readonly Repository $config) {}

    public function all(): array
    {
        if ($this->models !== null) {
            return $this->models;
        }

        $models = [];

        foreach ($this->config->get('eloquent-mcp.models', []) as $class => $options) {
            if (is_int($class)) {
                [$class, $options] = [$options, []];
            }

            if (! is_string($class) || ! is_a($class, Model::class, true)) {
                throw new InvalidArgumentException("[{$class}] is not an Eloquent model.");
            }

            $key = $options['as'] ?? class_basename($class);

            $models[$key] = new ExposedModel(
                $key,
                $class,
                $options,
                $this->config->get('eloquent-mcp.connection'),
                max(1, (int) $this->config->get('eloquent-mcp.max_string_length', 1000)),
            );
        }

        return $this->models = $models;
    }

    public function find(string $key): ?ExposedModel
    {
        return $this->all()[$key] ?? null;
    }

    public function forClass(string $class): ?ExposedModel
    {
        foreach ($this->all() as $model) {
            if ($model->class === $class) {
                return $model;
            }
        }

        return null;
    }

    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function maxRows(): int
    {
        return max(1, (int) $this->config->get('eloquent-mcp.max_rows', 50));
    }
}
