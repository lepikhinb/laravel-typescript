<?php

namespace Based\TypeScript\Commands;

use Based\TypeScript\TypeScriptGenerator;
use Illuminate\Console\Command;
use Spatie\LaravelPackageTools\Package;

class TypeScriptGenerateCommand extends Command
{
    public $signature = 'typescript:generate';

    public $description = 'Generate TypeScript definitions from PHP classes';

    public function handle()
    {
        $generator = new TypeScriptGenerator(
            ...config('typescript')
        );

        $generator->execute();

        $this->comment('TypeScript definitions generated successfully');
    }
}
