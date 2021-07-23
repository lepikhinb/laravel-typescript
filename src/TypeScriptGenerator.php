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
        public ?string $composerPath =  null
    ) {
        $this->composerPath ??= base_path('composer.json');
    }

    public function execute()
    {
        $types = $this->phpClasses()
            ->map(function (ReflectionClass $reflection) {
                $generator = collect($this->generators)
                    ->filter(fn (string $generator, string $baseClass) => $reflection->isSubclassOf($baseClass))
                    ->values()
                    ->first();

                if (!$generator) {
                    return null;
                }

                return (new $generator)->generate($reflection);
            })
            ->whereNotNull()
            ->join(PHP_EOL . PHP_EOL);

        file_put_contents($this->output, $types);
    }

    public function phpClasses(): Collection
    {
        $composer = json_decode(file_get_contents($this->composerPath));

        return collect($composer->autoload->{'psr-4'})
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
