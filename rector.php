<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function(RectorConfig $rectorConfig): void {
    $rectorConfig->parallel(processTimeout: 360);

    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/test',
    ]);

    $rectorConfig->sets([
        SetList::NAMING,
        SetList::CODE_QUALITY,
        SetList::PRIVATIZATION,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        LevelSetList::UP_TO_PHP_83,
    ]);

    // `default` is part of ConfigReader::enum()'s public named-argument API.
    $rectorConfig->skip([
        RenameParamToMatchTypeRector::class => [
            __DIR__ . '/src/ConfigReader.php',
        ],
    ]);
};
