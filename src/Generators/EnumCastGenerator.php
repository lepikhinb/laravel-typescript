<?php

declare(strict_types=1);

namespace Based\TypeScript\Generators;

use Based\TypeScript\Contracts\Generator;
use Based\TypeScript\Generators\AbstractGenerator;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumUnitCase;
use ReflectionEnumBackedCase;

class EnumCastGenerator
{
    protected ReflectionEnum $reflectionEnum;

    public function getDefinition(): ?string
    {
        return collect($this->reflectionEnum->getCases())
            ->map(function (ReflectionEnumUnitCase|ReflectionEnumBackedCase $case) {
                if ($case instanceof ReflectionEnumBackedCase) {
                    return sprintf("%s = '%s',", $case->getName(), $case->getValue()->name);
                } else {
                    return $case->getName() . ',';
                }
            })
            ->filter(fn (string $part) => !empty($part))
            ->join(PHP_EOL . '    ');
    }

    public function generate(ReflectionEnum $reflection): ?string
    {
        $this->reflectionEnum = $reflection;
        
        if (empty(trim($definition = $this->getDefinition()))) {
            return "export enum {$this->tsEnumName()}Enum {}" . PHP_EOL . PHP_EOL .
                "export const {$this->tsEnumName()}EnumArray {}" . PHP_EOL;
        }

        return <<<TS
        export enum {$this->tsEnumName()}Enum {
            $definition
        }
        
        export const {$this->tsEnumName()}EnumArray = Object.values({$this->tsEnumName()}Enum)
        
        TS;
    }

    protected function tsEnumName(): string
    {
        return str_replace('\\', '.', $this->reflectionEnum->getShortName());
    }
}
