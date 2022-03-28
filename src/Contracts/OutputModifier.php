<?php

declare(strict_types=1);

namespace Based\TypeScript\Contracts;

interface OutputModifier
{
    /**
     * Alter types declaration before writing it.
     *
     * @param string $content
     *
     * @return string
     */
    public function modify(string $content): string;
}
