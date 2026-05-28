<?php

declare(strict_types=1);

namespace SirixTest\ContainerResolver\TestAsset;

/**
 * @internal
 */
enum LogLevel: int
{
    case Debug = 100;
    case Info = 200;
    case Warning = 300;
}
