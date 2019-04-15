<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

use Psr\Container\ContainerExceptionInterface;

class ContainerUnsetNotAllowed extends \DomainException implements ContainerExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct(
            sprintf(
                '%s::%s() not allowed when using custom PSR-11 containers: can\'t unset %s.',
                Container::class,
                'offsetUnset',
                $id
            )
        );
    }
}
