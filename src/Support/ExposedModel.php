<?php

namespace SabiraKhan\EloquentMcp\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;

class ExposedModel
{
    private ?array $columns = null;

    private ?array $schema = null;

    public function __construct(
        public readonly string $key,
        public readonly string $class,
        private readonly array $options,
        private readonly ?string $connection,
        private readonly int $maxStringLength,
    ) {}

    public function model(): Model
    {
        $model = new $this->class;

        if ($this->connection !== null) {
            $model->setConnection($this->connection);
        }

        return $model;
    }

    public function query(): Builder
    {
        return $this->model()->newQuery();
    }

    public function description(): ?string
    {
        return $this->options['description'] ?? null;
    }

    public function table(): string
    {
        return $this->model()->getTable();
    }

    public function schema(): array
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        $model = $this->model();

        return $this->schema = collect(Schema::connection($model->getConnectionName())->getColumns($model->getTable()))
            ->keyBy('name')
            ->all();
    }

    public function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $model = $this->model();
        $columns = array_keys($this->schema());

        if (isset($this->options['columns'])) {
            $columns = array_values(array_intersect($columns, $this->options['columns']));
        }

        if ($model->getVisible() !== []) {
            $columns = array_values(array_intersect($columns, $model->getVisible()));
        }

        return $this->columns = array_values(array_diff($columns, $model->getHidden()));
    }

    public function hasColumn(string $column): bool
    {
        return in_array($column, $this->columns(), true);
    }

    public function relationNames(): array
    {
        return array_values(array_filter(
            $this->options['relations'] ?? [],
            fn (string $name) => $this->relation($name) !== null,
        ));
    }

    public function relation(string $name): ?Relation
    {
        if (! in_array($name, $this->options['relations'] ?? [], true) || ! method_exists($this->class, $name)) {
            return null;
        }

        $method = new ReflectionMethod($this->class, $name);

        if (! $method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
            return null;
        }

        $type = $method->getReturnType();

        if ($type instanceof ReflectionNamedType && ! is_a($type->getName(), Relation::class, true)) {
            return null;
        }

        $relation = $this->model()->{$name}();

        return $relation instanceof Relation ? $relation : null;
    }

    public function serialize(Model $model): array
    {
        $attributes = Arr::only($model->attributesToArray(), $this->columns());

        return array_map(fn ($value) => $this->clean($value), $attributes);
    }

    private function clean(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_string($value) && mb_strlen($value) > $this->maxStringLength) {
            return Str::limit($value, $this->maxStringLength);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->clean($item), $value);
        }

        return $value;
    }
}
