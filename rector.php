<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/public',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php83: true)
    ->withCodeQualityLevel(1)
    ->withDeadCodeLevel(1)
    ->withTypeCoverageLevel(1)
    ->withSkip([
        ClosureToArrowFunctionRector::class,
    ]);
