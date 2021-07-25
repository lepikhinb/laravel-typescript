<?php

namespace Based\TypeScript\Generators;

use Based\TypeScript\Definitions\TypeScriptProperty;
use Based\TypeScript\Definitions\TypeScriptType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class ModelGenerator extends AbstractGenerator
{
    protected Model $model;
    /** @var Collection<Column> */
    protected Collection $columns;

    public function getDefinition(): string
    {
        return collect([
            $this->getProperties(),
            $this->getRelations(),
            $this->getAccessors(),
        ])
            ->filter(fn (string $part) => !empty($part))
            ->join(PHP_EOL . '        ');
    }

    protected function boot(): void
    {
        $this->model = $this->reflection->newInstance();
        $this->columns = collect(
            $this->model->getConnection()
                ->getDoctrineSchemaManager()
                ->listTableColumns($this->model->getConnection()->getTablePrefix() . $this->model->getTable())
        );
    }

    protected function getProperties(): string
    {
        return $this->columns->map(function (Column $column) {
            return (string) new TypeScriptProperty(
                name: $column->getName(),
                types: $this->getPropertyType($column->getType()->getName()),
                nullable: !$column->getNotnull()
            );
        })
            ->join(PHP_EOL . '        ');
    }

    protected function getAccessors(): string
    {
        return $this->getMethods()
            ->filter(fn (ReflectionMethod $method) => Str::startsWith($method->getName(), 'get'))
            ->filter(fn (ReflectionMethod $method) => Str::endsWith($method->getName(), 'Attribute'))
            ->mapWithKeys(function (ReflectionMethod $method) {
                $property = (string) Str::of($method->getName())
                    ->between('get', 'Attribute')
                    ->snake();

                return [$property => $method];
            })
            ->reject(function (ReflectionMethod $method, string $property) {
                return $this->columns->contains(fn (Column $column) => $column->getName() == $property);
            })
            ->map(function (ReflectionMethod $method, string $property) {
                return (string) new TypeScriptProperty(
                    name: $property,
                    types: TypeScriptType::fromMethod($method),
                    optional: true,
                    readonly: true
                );
            })
            ->join(PHP_EOL . '        ');
    }

    protected function getRelations(): string
    {
        return $this->getMethods()
            ->filter(function (ReflectionMethod $method) {
                try {
                    return $method->invoke($this->model) instanceof Relation;
                } catch (Throwable $e) {
                    return false;
                }
            })
            // [TODO] Resolve trait/parent relations as well (e.g. DatabaseNotification)
            // skip traits for awhile
            ->filter(function (ReflectionMethod $method) {
                return collect($this->reflection->getTraits())
                    ->filter(function (ReflectionClass $trait) use ($method) {
                        return $trait->hasMethod($method->name);
                    })
                    ->isEmpty();
            })
            ->map(function (ReflectionMethod $method) {
                return (string) new TypeScriptProperty(
                    name: $method->getName(),
                    types: $this->getRelationType($method),
                    optional: true,
                    nullable: true
                );
            })
            ->join(PHP_EOL . '        ');
    }

    protected function getMethods(): Collection
    {
        return collect($this->reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn (ReflectionMethod $method) => $method->isStatic())
            ->reject(fn (ReflectionMethod $method) => $method->getNumberOfParameters());
    }

    protected function getPropertyType(string $type): string | array
    {
        return match ($type) {
            Types::ARRAY => [TypeScriptType::array(), TypeScriptType::ANY],
            Types::ASCII_STRING => TypeScriptType::STRING,
            Types::BIGINT => TypeScriptType::NUMBER,
            Types::BINARY => TypeScriptType::STRING,
            Types::BLOB => TypeScriptType::STRING,
            Types::BOOLEAN => TypeScriptType::BOOLEAN,
            Types::DATE_MUTABLE => TypeScriptType::STRING,
            Types::DATE_IMMUTABLE => TypeScriptType::STRING,
            Types::DATEINTERVAL => TypeScriptType::STRING,
            Types::DATETIME_MUTABLE => TypeScriptType::STRING,
            Types::DATETIME_IMMUTABLE => TypeScriptType::STRING,
            Types::DATETIMETZ_MUTABLE => TypeScriptType::STRING,
            Types::DATETIMETZ_IMMUTABLE => TypeScriptType::STRING,
            Types::DECIMAL => TypeScriptType::NUMBER,
            Types::FLOAT => TypeScriptType::NUMBER,
            Types::GUID => TypeScriptType::STRING,
            Types::INTEGER => TypeScriptType::NUMBER,
            Types::JSON => [TypeScriptType::array(), TypeScriptType::ANY],
            Types::OBJECT => TypeScriptType::ANY,
            Types::SIMPLE_ARRAY => [TypeScriptType::array(), TypeScriptType::ANY],
            Types::SMALLINT => TypeScriptType::NUMBER,
            Types::STRING => TypeScriptType::STRING,
            Types::TEXT => TypeScriptType::STRING,
            Types::TIME_MUTABLE => TypeScriptType::NUMBER,
            Types::TIME_IMMUTABLE => TypeScriptType::NUMBER,
            default => TypeScriptType::ANY,
        };
    }

    protected function getRelationType(ReflectionMethod $method): string
    {
        $relationReturn = $method->invoke($this->model);
        $related = str_replace('\\', '.', get_class($relationReturn->getRelated()));

        return match (get_class($relationReturn)) {
            HasMany::class => TypeScriptType::array($related),
            BelongsToMany::class => TypeScriptType::array($related),
            HasManyThrough::class => TypeScriptType::array($related),
            MorphMany::class => TypeScriptType::array($related),
            MorphToMany::class => TypeScriptType::array($related),
            HasOne::class => $related,
            BelongsTo::class => $related,
            MorphOne::class => $related,
            HasOneThrough::class => $related,
            default => 'any',
        };
    }
}
