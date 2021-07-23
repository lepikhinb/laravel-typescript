<?php

namespace Based\TypeScript\Generators;

use ReflectionClass;
use Based\TypeScript\Contracts\Generator;

abstract class AbstractGenerator implements Generator
{
    protected ReflectionClass $reflection;

    public function generate(ReflectionClass $reflection): string
    {
        $this->reflection = $reflection;
        $this->boot();

        return <<<TS
        export namespace {$this->tsNamespace()}
        {
            export interface {$this->tsClassName()} {
                {$this->getDefinition()}
            }
        }
        TS;
    }

    protected function boot(): void
    {
        //
    }

    protected function tsNamespace(): string
    {
        return str_replace('\\', '.', $this->reflection->getNamespaceName());
    }

    protected function tsClassName(): string
    {
        return str_replace('\\', '.', $this->reflection->getShortName());
    }
}
