<?php

namespace Based\TypeScript\Definitions;

use ReflectionMethod;
use ReflectionUnionType;

class TypeScriptType
{
    public const STRING = 'string';
    public const NUMBER = 'number';
    public const BOOLEAN = 'boolean';
    public const ANY = 'any';
    public const NULL = 'null';

    public static function array(string $type = self::ANY): string
    {
        return "Array<{$type}>";
    }

    public static function fromMethod(ReflectionMethod $method): array
    {
        $types = $method->getReturnType() instanceof ReflectionUnionType
            ? $method->getReturnType()->getTypes()
            : (string)$method->getReturnType();

        return collect($types)
            ->map(function (string $type) {
                return match ($type) {
                    'int' => self::NUMBER,
                    'float' => self::NUMBER,
                    'string' => self::STRING,
                    'array' => self::array(),
                    'object' => self::ANY,
                    'null' => self::NULL,
                    'bool' => self::BOOLEAN,
                    default => self::ANY,
                };
            })
            ->toArray();
    }
}
