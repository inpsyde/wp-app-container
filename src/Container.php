<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Inpsyde\WpContext;
use Pimple;
use Pimple\Exception\UnknownIdentifierException;
use Psr\Container\ContainerInterface;

/**
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 */
final class Container implements ContainerInterface
{
    /**
     * @var SiteConfig
     */
    private $config;

    /**
     * @var WpContext
     */
    private $context;

    /**
     * @var Pimple\Container|null
     */
    private $pimple;

    /**
     * @var ContainerInterface[]
     */
    private $containers = [];

    /**
     * @param SiteConfig|null $config
     * @param WpContext|null $context
     * @param ContainerInterface ...$containers
     */
    public function __construct(
        SiteConfig $config = null,
        WpContext $context = null,
        ContainerInterface ...$containers
    ) {

        $this->config = $config ?? new EnvConfig();
        $this->context = $context ?? WpContext::determine();
        $this->containers = $containers;
        if (!$containers) {
            $this->ensurePimple();
        }
    }

    /**
     * @param SiteConfig $config
     * @return Container
     */
    public function withSiteConfig(SiteConfig $config): Container
    {
        $instance = clone $this;
        $instance->config = $config;

        return $instance;
    }

    /**
     * @return SiteConfig
     */
    public function config(): SiteConfig
    {
        return $this->config;
    }

    /**
     * @return WpContext
     */
    public function context(): WpContext
    {
        return $this->context;
    }

    /**
     * @param ContainerInterface $container
     * @return Container
     */
    public function addContainer(ContainerInterface $container): Container
    {
        $this->containers[] = $container;

        return $this;
    }

    /**
     * @param string $id
     * @param callable $factory
     * @return void
     */
    public function addService(string $id, callable $factory): void
    {
        try {
            $this->ensurePimple();
            /** @psalm-suppress PossiblyNullReference */
            $this->pimple[$id] = $this->wrapCallback($factory);
        } catch (\Throwable $throwable) {
            do_action(App::ACTION_ERROR, $throwable);

            throw $throwable;
        }
    }

    /**
     * @param string $id
     * @param callable $extender
     * @return void
     */
    public function extendService(string $id, callable $extender): void
    {
        try {
            $this->ensurePimple();
            /** @psalm-suppress PossiblyNullReference */
            $this->pimple->extend(
                $id,
                /**
                 * @psalm-suppress MissingClosureParamType
                 * @psalm-suppress MissingClosureReturnType
                 * @psalm-suppress MixedFunctionCall
                 */
                function ($service) use (&$extender) {
                    return $extender($service, $this);
                }
            );
        } catch (\Throwable $throwable) {
            do_action(App::ACTION_ERROR, $throwable);

            throw $throwable;
        }
    }

    /**
     * @param string $id
     * @param callable $callable
     */
    public function addFactory(string $id, callable $callable): void
    {
        try {
            $this->ensurePimple();
            /** @psalm-suppress PossiblyNullReference */
            $this->pimple[$id] = $this->pimple->factory($this->wrapCallback($callable));
        } catch (\Throwable $throwable) {
            do_action(App::ACTION_ERROR, $throwable);

            throw $throwable;
        }
    }

    /**
     * @param mixed $id
     * @return mixed
     *
     * @psalm-suppress MissingReturnType
     * @psalm-suppress MissingParamType
     * @psalm-suppress RedundantConditionGivenDocblockType
     */
    public function get($id)
    {
        $this->assertString($id, __METHOD__);

        try {
            foreach ($this->containers as $container) {
                if ($container->has($id)) {
                    return $container->get($id);
                }
            }

            throw new UnknownIdentifierException($id);
        } catch (\Throwable $throwable) {
            do_action(App::ACTION_ERROR, $throwable);

            throw $throwable;
        }
    }

    /**
     * @param mixed $id
     * @return bool
     *
     * @psalm-suppress MissingParamType
     * @psalm-suppress RedundantConditionGivenDocblockType
     */
    public function has($id): bool
    {
        $this->assertString($id, __METHOD__);

        try {
            foreach ($this->containers as $container) {
                if ($container->has($id)) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $throwable) {
            do_action(App::ACTION_ERROR, $throwable);

            throw $throwable;
        }
    }

    /**
     * @return void
     */
    private function ensurePimple(): void
    {
        if (!$this->pimple) {
            $pimple = new Pimple\Container();
            $this->containers[] = new Pimple\Psr11\Container($pimple);
            $this->pimple = $pimple;
        }
    }

    /**
     * Simulating type declaration, which is not possible due to PSR-11 interface.
     *
     * @param mixed $value Should be string
     * @param string $method
     * @return void
     *
     * @psalm-suppress MissingParamType
     * @psalm-assert string $value
     */
    private function assertString($value, string $method): void
    {
        if (!is_string($value)) {
            throw new \TypeError(
                sprintf(
                    'Argument 1 passed to %s() must be a string, %s given.',
                    $method,
                    gettype($value)
                )
            );
        }
    }

    /**
     * @param callable $factory
     * @return \Closure
     */
    private function wrapCallback(callable $factory): \Closure
    {
        /**
         * @psalm-suppress MissingClosureReturnType
         * @psalm-suppress MixedFunctionCall
         */
        return function () use (&$factory) {
            return $factory($this);
        };
    }
}
