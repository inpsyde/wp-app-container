<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Inpsyde\WpContext;

/**
 * @deprecated Will be removed in next major, replaced by WpContext
 */
final class Context extends WpContext implements \JsonSerializable
{
    /**
     * @return WpContext
     */
    public static function create(): WpContext
    {
        return static::determine();
    }
}
