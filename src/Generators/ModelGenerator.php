<?php

namespace Based\TypeScript\Generators;

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
            $type = $this->getPropertyType($column->getType()->getName());

            if (!$column->getNotnull()) {
                $type .= ' | null';
            }

            return "{$column->getName()}: {$type};";
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
                $type = $this->getNativeType((string) $method->getReturnType());

                return "readonly {$property}?: {$type};";
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
                return "{$method->getName()}?: {$this->getRelationTypehint($method)};";
            })
            ->join(PHP_EOL . '        ');
    }

    protected function getMethods(): Collection
    {
        return collect($this->reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn (ReflectionMethod $method) => $method->isStatic())
            ->reject(fn (ReflectionMethod $method) => $method->getNumberOfParameters());
    }

    protected function getPropertyType(string $type): string
    {
        return match ($type) {
            Types::ARRAY => 'Array<any> | any',
            Types::ASCII_STRING => 'string',
            Types::BIGINT => 'number',
            Types::BINARY => 'string',
            Types::BLOB => 'string',
            Types::BOOLEAN => 'boolean',
            Types::DATE_MUTABLE => 'string',
            Types::DATE_IMMUTABLE => 'string',
            Types::DATEINTERVAL => 'string',
            Types::DATETIME_MUTABLE => 'string',
            Types::DATETIME_IMMUTABLE => 'string',
            Types::DATETIMETZ_MUTABLE => 'string',
            Types::DATETIMETZ_IMMUTABLE => 'string',
            Types::DECIMAL => 'number',
            Types::FLOAT => 'number',
            Types::GUID => 'string',
            Types::INTEGER => 'number',
            Types::JSON => 'Array<any> | any',
            Types::OBJECT => 'object',
            Types::SIMPLE_ARRAY => 'Array<any> | any',
            Types::SMALLINT => 'number',
            Types::STRING => 'string',
            Types::TEXT => 'string',
            Types::TIME_MUTABLE => 'number',
            Types::TIME_IMMUTABLE => 'number',
            default => 'any',
        };
    }

    protected function getNativeType(?string $type): string
    {
        return match ($type) {
            'int' => 'number',
            'float' => 'number',
            'string' => 'string',
            'array' => 'Array<any> | any',
            'object' => 'any',
            'null' => 'null',
            default => 'any',
        };
    }

    protected function getRelationTypehint(ReflectionMethod $method): string
    {
        $relationReturn = $method->invoke($this->model);
        $related = get_class($relationReturn->getRelated());

        return match (get_class($relationReturn)) {
            HasMany::class => $this->getManyHint($related),
            BelongsToMany::class => $this->getManyHint($related),
            HasManyThrough::class => $this->getManyHint($related),
            MorphMany::class => $this->getManyHint($related),
            MorphToMany::class => $this->getManyHint($related),
            HasOne::class => $this->getOneHint($related),
            BelongsTo::class => $this->getOneHint($related),
            MorphOne::class => $this->getOneHint($related),
            HasOneThrough::class => $this->getOneHint($related),
            default => 'any',
        };
    }

    protected function getManyHint(string $related): string
    {
        return (string) Str::of($related)
            ->replace('\\', '.')
            ->prepend('Array<')
            ->append('>')
            ->append(' | null');
    }

    protected function getOneHint(string $related): string
    {
        return (string) Str::of($related)
            ->replace('\\', '.')
            ->append(' | null');
    }
}
