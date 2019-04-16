<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

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
     * @var Context
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
     * @param Context|null $context
     * @param ContainerInterface ...$containers
     */
    public function __construct(
        SiteConfig $config = null,
        Context $context = null,
        ContainerInterface ...$containers
    ) {

        if (!$containers) {
            $pimple = new Pimple\Container();
            $containers = [new Pimple\Psr11\Container($pimple)];
            $this->pimple = $pimple;
        }

        $this->config = $config ?? new EnvConfig();
        $this->context = $context ?? Context::create();
        $this->containers = $containers;
    }

    /**
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
     * @return Context
     */
    public function context(): Context
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
    public function addService(string $id, callable $factory)
    {
        try {
            $this->ensurePimple();
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
    public function extendService(string $id, callable $extender)
    {
        try {
            $this->ensurePimple();
            // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
            $this->pimple->extend(
                $id,
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
            // @phan-suppress-next-line PhanPossiblyNonClassMethodCall
            $this->pimple[$id] = $this->pimple->factory($this->wrapCallback($callable));
        } catch (\Throwable $throwable) {
            do_action(App::ACTION_ERROR, $throwable);

            throw $throwable;
        }
    }

    /**
     * @param string $id
     * @return mixed
     *
     * phpcs:disable Generic.Metrics.NestingLevel
     */
    public function get($id)
    {
        // phpcs:enable Generic.Metrics.NestingLevel
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
     * @param string $id
     * @return bool
     *
     * phpcs:disable Generic.Metrics.NestingLevel
     */
    public function has($id)
    {
        // phpcs:enable Generic.Metrics.NestingLevel
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
     */
    private function assertString($value, string $method)
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
     * @param \callable $factory
     * @return \Closure
     */
    private function wrapCallback(callable $factory): \Closure
    {
        // @phan-suppress-next-line PhanUnreferencedClosure
        return function () use (&$factory) {
            return $factory($this);
        };
    }
}
