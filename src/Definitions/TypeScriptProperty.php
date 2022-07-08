<?php

declare(strict_types=1);

namespace Based\TypeScript\Definitions;

use Illuminate\Support\Collection;

class TypeScriptProperty
{
    public function __construct(
        public string $name,
        public string|array $types,
        public bool $optional = false,
        public bool $readonly = false,
        public bool $nullable = false
    )
    {
    }

    public function getTypes(): string
    {
        return collect($this->types)
            ->when($this->nullable, fn(Collection $types) => $types->push(TypeScriptType::NULL))
            ->join(' | ', '');
    }

    public function __toString(): string
    {
        return collect($this->name)
            ->when($this->readonly, fn(Collection $definition) => $definition->prepend('readonly '))
            ->when($this->optional, fn(Collection $definition) => $definition->push('?'))
            ->push(': ')
            ->push($this->getTypes())
            ->push(';')
            ->join('');
    }
}
