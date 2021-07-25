# Laravel Typescript

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lepikhinb/laravel-typescript.svg?style=flat-square)](https://packagist.org/packages/lepikhinb/laravel-typescript)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/lepikhinb/laravel-typescript/run-tests?label=tests)](https://github.com/lepikhinb/laravel-typescript/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/lepikhinb/laravel-typescript/Check%20&%20fix%20styling?label=code%20style)](https://github.com/lepikhinb/laravel-typescript/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/lepikhinb/laravel-typescript.svg?style=flat-square)](https://packagist.org/packages/lepikhinb/laravel-typescript)

---
xxx
---

yyy
zzz

## Installation

You can install the package via composer:

```bash
composer require based/laravel-typescript
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="Based\TypeScript\TypeScriptServiceProvider" --tag="typescript-config"
```

This is the contents of the published config file:

```php
return [
    'generators' => [
        Model::class => ModelGenerator::class,
    ],

    'output' => resource_path('js/models.d.ts'),

    'autoloadDev' => false,
];

```

## Usage

```bash
php artisan typescript:generate
```

## Testing

```bash
composer test
```

## Credits

- [Boris Lepikhin](https://github.com/lepikhinb)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
