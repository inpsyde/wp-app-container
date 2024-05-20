<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Inpsyde\WpContext;
use Pimple;
use Pimple\Exception\UnknownIdentifierException;
use Psr\Container\ContainerInterface;

final class Container implements ContainerInterface
{
    /** @var list<ContainerInterface> */
    private array $containers;
    private SiteConfig $config;
    private WpContext $context;
    private ?Pimple\Container $pimple = null;

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
        $this->containers = array_values($containers);
        if ($containers === []) {
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
            $this->pimple->extend(
                $id,
                /**
                 * @param mixed $service
                 * @return mixed
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
            $this->pimple[$id] = $this->pimple->factory($this->wrapCallback($callable));
        } catch (\Throwable $throwable) {
            do_action(App::ACTION_ERROR, $throwable);

            throw $throwable;
        }
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function get(string $id)
    {
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
     */
    public function has(string $id): bool
    {
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
     *
     * @psalm-assert Pimple\Container $this->pimple
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
     * @param callable $factory
     * @return \Closure
     */
    private function wrapCallback(callable $factory): \Closure
    {
        /**
         * @return mixed
         * @psalm-suppress MissingClosureReturnType
         */
        return function () use (&$factory) {
            return $factory($this);
        };
    }
}
