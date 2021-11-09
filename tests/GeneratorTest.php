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
        $this->assertTrue(strpos($result, 'sub_category?: Based.TypeScript.Tests.Models.Category | null;') > -1);
        $this->assertTrue(strpos($result, 'products_count?: number | null;') > -1);

        unlink($output);
    }
}
