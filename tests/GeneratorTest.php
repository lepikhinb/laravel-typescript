<?php

namespace Based\TypeScript\Tests;

use Based\TypeScript\TypeScriptGenerator;

class GeneratorTest extends TestCase
{
    /** @test */
    public function it_works()
    {
        $output = @tempnam('/tmp', 'models.d.ts');

        $generator = new TypeScriptGenerator(
            generators: config('typescript.generators'),
            output: $output,
            autoloadDev: true
        );

        $generator->execute();

        $this->assertFileExists($output);

        $result = file_get_contents($output);

        $this->assertEquals(3, substr_count($result, 'interface'));
        $this->assertTrue(str_contains($result, 'sub_category?: Based.TypeScript.Tests.Models.Category | null;'));
        $this->assertTrue(str_contains($result, 'products_count?: number | null;'));

        unlink($output);
    }
}
