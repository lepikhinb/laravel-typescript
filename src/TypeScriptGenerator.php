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
        public bool $autoloadDev
    ) {
    }

    public function execute()
    {
        $types = $this->phpClasses()
            ->groupBy(fn (ReflectionClass $reflection) => $reflection->getNamespaceName())
            ->map(fn (Collection $reflections, string $namespace) => $this->makeNamespace($namespace, $reflections))
            ->reject(fn (string $namespaceDefinition) => empty($namespaceDefinition))
            ->join(PHP_EOL);

        file_put_contents($this->output, $types);
    }

    protected function makeNamespace(string $namespace, Collection $reflections): string
    {
        return $reflections->map(fn (ReflectionClass $reflection) => $this->makeInterface($reflection))
            ->whereNotNull()
            ->whenNotEmpty(function (Collection $definitions) use ($namespace) {
                $tsNamespace = str_replace('\\', '.', $namespace);

                return $definitions->prepend("declare namespace {$tsNamespace} {")->push('}');
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
                        } catch (ReflectionException $e) {
                            //
                        }
                    })
                    ->map(fn (string $className) => new ReflectionClass($className))
                    ->reject(fn (ReflectionClass $reflection) => $reflection->isAbstract())
                    ->values();
            });
    }
}
