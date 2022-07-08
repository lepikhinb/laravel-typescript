<?php

declare(strict_types=1);

namespace Based\TypeScript;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use ReflectionEnum;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Based\TypeScript\Contracts\OutputModifier;

class TypeScriptGenerator
{
    public function __construct(
        public array $generators,
        public string $output,
        public bool $autoloadDev,
        public array $paths = [],
        public bool $runtimeEnums = false,
        public ?string $runtimeEnumsOutput = null,
        public array $outputModifiers = [],
        public array $runtimeEnumsOutputModifiers = []
    ) {
    }

    public function execute()
    {
        $types = $this->phpClasses()
            ->groupBy(fn (ReflectionClass $reflection) => $reflection->getNamespaceName())
            ->map(fn (Collection $reflections, string $namespace) => $this->makeNamespace($namespace, $reflections))
            ->reject(fn (string $namespaceDefinition) => empty($namespaceDefinition))
            ->prepend(
                <<<END
                /**
                 * This file is auto generated using 'php artisan typescript:generate'
                 *
                 * Changes to this file will be lost when the command is run again
                 */

                END
            )
            ->join(PHP_EOL);

        file_put_contents($this->output, $types);

        // Get previously generated types for classes.
        $types = file_get_contents($this->output);

        // Alter generated content using OutputModifiers.
        if (!empty(($this->outputModifiers))) {
            collect($this->outputModifiers)
                ->map(fn (string $modifierClass) => new $modifierClass())
                ->each(function (OutputModifier $modifier) use (&$types) {
                    $types = $modifier->modify($types);
                });
        }
        file_put_contents($this->output, $types);

        if ($this->runtimeEnums) {
            $enumTypes = $this->phpEnums()
                ->groupBy(fn(ReflectionEnum $reflection) => $reflection->getNamespaceName())
                ->map(fn(Collection $reflections, string $namespace) => $this->makeRuntimeEnums($namespace, $reflections))
                ->reject(fn(string $namespaceDefinition) => empty($namespaceDefinition))
                ->prepend(
                    <<<END
                /**
                 * This file is auto generated using 'php artisan typescript:generate'
                 *
                 * Changes to this file will be lost when the command is run again
                 */

                END
                )
                ->join(PHP_EOL);

            // Alter generated content using Enum OutputModifiers.
            if (!empty(($this->runtimeEnumsOutputModifiers))) {
                collect($this->runtimeEnumsOutputModifiers)
                    ->map(fn (string $modifierClass) => new $modifierClass())
                    ->each(function (OutputModifier $modifier) use (&$enumTypes) {
                        $enumTypes = $modifier->modify($enumTypes);
                    });
            }
            file_put_contents($this->runtimeEnumsOutput, $enumTypes);
        }
    }

    protected function makeNamespace(string $namespace, Collection $reflections): string
    {
        return $reflections->map(fn (ReflectionClass $reflection) => $this->makeInterface($reflection))
            ->whereNotNull()
            ->whenNotEmpty(function (Collection $definitions) use ($namespace) {
                $tsNamespace = str_replace('\\', '.', $namespace);

                return $definitions->prepend("declare namespace {$tsNamespace} {")->push('}' . PHP_EOL);
            })
            ->join(PHP_EOL);
    }

    protected function makeInterface(ReflectionClass $reflection): ?string
    {
        $generator = collect($this->generators)
            ->filter(fn (string $generator, string $baseClass) => $reflection->isSubclassOf($baseClass))
            ->values()
            ->first();

        if (!$generator) {
            return null;
        }

        return (new $generator)->generate($reflection);
    }

    protected function makeRuntimeEnums(string $namespace, Collection $reflections): string
    {
        return $reflections->map(fn(ReflectionEnum $reflection) => $this->makeEnum($reflection))
            ->whereNotNull()
            ->join(PHP_EOL);
    }

    protected function makeEnum(ReflectionEnum $reflection): ?string
    {
        $generator = collect($this->generators)
            ->filter(
                fn(string $generator, string $interface) => interface_exists($interface) &&
                    $reflection->implementsInterface($interface)
            )
            ->values()
            ->first();
        if (!$generator) {
            return null;
        }
        return (new $generator)->generate($reflection);
    }

    protected function phpClasses(): Collection
    {
        $composer = json_decode(file_get_contents(realpath('composer.json')));

        return collect($composer->autoload->{'psr-4'})
            ->when($this->autoloadDev, function (Collection $paths) use ($composer) {
                return $paths->merge(
                    collect($composer->{'autoload-dev'}?->{'psr-4'})
                );
            })
            ->merge($this->paths)
            ->flatMap(function (string $path, string $namespace) {
                return collect((new Finder)->in($path)->name('*.php')->files())
                    ->map(function (SplFileInfo $file) use ($path, $namespace) {
                        return $namespace . str_replace(
                            ['/', '.php'],
                            ['\\', ''],
                            Str::after($file->getRealPath(), realpath($path) . DIRECTORY_SEPARATOR)
                        );
                    })
                    ->filter(function (string $className) {
                        try {
                            new ReflectionClass($className);

                            return true;
                        } catch (ReflectionException) {
                            return false;
                        }
                    })
                    ->map(fn (string $className) => new ReflectionClass($className))
                    ->reject(fn (ReflectionClass $reflection) => $reflection->isAbstract() || $reflection->isEnum())
                    ->values();
            });
    }

    protected function phpEnums(): Collection
    {
        $composer = json_decode(file_get_contents(realpath('composer.json')));

        return collect($composer->autoload->{'psr-4'})
            ->when($this->autoloadDev, function (Collection $paths) use ($composer) {
                return $paths->merge(
                    collect($composer->{'autoload-dev'}?->{'psr-4'})
                );
            })
            ->merge($this->paths)
            ->flatMap(function (string $path, string $namespace) {
                return collect((new Finder)->in($path)->name('*.php')->files())
                    ->map(function (\SplFileInfo $file) use ($path, $namespace) {
                        return $namespace . str_replace(
                                ['/', '.php'],
                                ['\\', ''],
                                Str::after($file->getRealPath(), realpath($path) . DIRECTORY_SEPARATOR)
                            );
                    })
                    ->filter(fn(string $enumName) => enum_exists($enumName))
                    ->map(fn(string $enumName) => new ReflectionEnum($enumName))
                    ->values();
            });
    }
}
