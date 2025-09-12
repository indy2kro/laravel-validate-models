<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use RectorLaravel\Set\LaravelSetList;
use RectorLaravel\Rector\Class_\ModelCastsPropertyToCastsMethodRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . '/src', __DIR__ . '/tests']);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        PHPUnitSetList::PHPUNIT_100,
        LaravelSetList::LARAVEL_80,
        LaravelSetList::LARAVEL_90,
        LaravelSetList::LARAVEL_100,
        LaravelSetList::LARAVEL_110,
    ]);

    $rectorConfig->skip([
        ModelCastsPropertyToCastsMethodRector::class => [
            'tests/ColumnCastCheckerTest.php',
        ],
    ]);

    // Keep diffs predictable
    $rectorConfig->importNames();
    $rectorConfig->disableParallel(); // CI consistency
};
