<?php

declare(strict_types=1);

namespace SirixTest\ContainerResolver\TestAsset;

/**
 * @internal
 */
enum StringDriver: string
{
    case Bearer = 'bearer';
    case Cookie = 'cookie';
}
