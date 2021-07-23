<?php

namespace Based\TypeScript\Tests;

use Based\TypeScript\TypeScriptGenerator;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFileExists;

class GeneratorTest extends TestCase
{
    /** @test */
    public function it_works()
    {
        $output = @tempnam('/tmp', 'types.d.ts');

        $generator = new TypeScriptGenerator(
            config('typescript.generators'),
            $output,
            __DIR__ . '/../composer.json'
        );

        $generator->execute();

        assertFileExists($output);

        assertEquals(3, substr_count(file_get_contents($output), 'interface'));

        unlink($output);
    }
}
