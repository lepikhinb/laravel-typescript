<?php

use Based\TypeScript\Generators\ModelGenerator;
use Illuminate\Database\Eloquent\Model;

return [
    'generators' => [
        Model::class => ModelGenerator::class,
    ],

    'output' => resource_path('js/models.d.ts'),

    'autoloadDev' => false,
];
