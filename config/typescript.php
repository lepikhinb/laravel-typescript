<?php

use Based\TypeScript\Generators\EnumCastGenerator;
use Based\TypeScript\Generators\ModelGenerator;
use Illuminate\Database\Eloquent\Model;
use Based\TypeScript\Contracts\ModelEnum;

return [
    'generators' => [
        Model::class => ModelGenerator::class,
        /**
         * To enable Enum cast codegen, 
         * Enums must implement the Based\TypeScript\Contracts\ModelEnum interface.
         */
        // ModelEnum::class => EnumCastGenerator::class,
    ],

    'paths' => [
        //
    ],

    'customRules' => [
        // \App\Rules\MyCustomRule::class => 'string',
        // \App\Rules\MyOtherCustomRule::class => ['string', 'number'],
    ],

    'output' => resource_path('js/models.d.ts'),

    'autoloadDev' => false,

    /**
     * Generate a TypeScript enum (that will be available at runtime) for every model cast.
     */
    'runtimeEnums' => true,

    /**
     * File to write the generated TS enums to.
     */
    'runtimeEnumsOutput' => resource_path('js/model-enums.ts'),

    /**
     * Modifiers can alter the generated declaration content before writing it to the file.
     * Modifiers must implement Utils\TypeScript\OutputModifiers\OutputModifier contract.
     */
    'outputModifiers' => [
    ],

    /**
     * Enum Modifiers can alter the generated Enums declaration before merging it in the global declaration content.
     * Enum Modifiers must implement Utils\TypeScript\OutputModifiers\OutputModifier contract.
     */
    'runtimeEnumsOutputModifiers' => [
    ],
];
