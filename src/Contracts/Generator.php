<?php

declare(strict_types=1);

namespace Based\TypeScript\Contracts;

use ReflectionClass;

interface Generator
{
    public function generate(ReflectionClass $reflection): ?string;

    public function getDefinition(): ?string;
}
