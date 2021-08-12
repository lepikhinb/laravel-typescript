<?php

namespace Based\TypeScript\Commands;

use Based\TypeScript\TypeScriptGenerator;
use Illuminate\Console\Command;

class TypeScriptGenerateCommand extends Command
{
    public $signature = 'typescript:generate';

    public $description = 'Generate TypeScript definitions from PHP classes';

    public function handle()
    {
        $generator = new TypeScriptGenerator(
            generators: config('typescript.generators', []),
            paths: config('typescript.paths', []),
            output: config('typescript.output', resource_path('js/models.d.ts')),
            autoloadDev: config('typescript.autoloadDev', false),
        );

        $generator->execute();

        $this->comment('TypeScript definitions generated successfully');
    }
}
