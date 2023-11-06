<?php

namespace Based\TypeScript;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class TypeScriptGenerator
{
    public function __construct(
        public array $generators,
        public string $output,
        public bool $autoloadDev,
        public array $paths = []
    ) {
    }

    public function execute($multiple = false)
    {
        $phpClasses = $this->phpClasses();
        $typeScriptDefinitions = [];

        foreach ($phpClasses as $reflection) {
            $namespace = $reflection->getNamespaceName();
            $className = $reflection->getShortName();

            $typeScriptDefinition = $this->makeNamespace($namespace, collect([$reflection]));

            if (!empty($typeScriptDefinition)) {
                $typeScriptDefinition = <<<END
                /**
                 * This file is auto generated using 'php artisan typescript:generate'
                 *
                 * Changes to this file will be lost when the command is run again
                 */

                END
                . $typeScriptDefinition;

                if ($multiple) {
                    file_put_contents($this->output.'/'.$className . '.ts', $typeScriptDefinition);
                } else {
                    $typeScriptDefinitions[] = $typeScriptDefinition;
                }
            }
        }

        if (!$multiple && !empty($typeScriptDefinitions)) {
            file_put_contents($this->output, implode(PHP_EOL, $typeScriptDefinitions));
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
                    ->reject(fn (ReflectionClass $reflection) => $reflection->isAbstract())
                    ->values();
            });
    }
}
