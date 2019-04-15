<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

use Pimple;
use Pimple\Exception\UnknownIdentifierException;
use Psr\Container\ContainerInterface;

/**
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 */
final class Container implements ContainerInterface, \ArrayAccess
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
     * @var bool
     */
    private $hasCustomContainers = true;

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
            $this->hasCustomContainers = false;
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
        $this->hasCustomContainers = true;

        return $this;
    }

    /**
     * @param string $id
     * @param mixed $value
     * @return void
     */
    public function offsetSet($id, $value)
    {
        try {
            $this->assertString($id, __METHOD__);
            if (!$this->pimple) {
                $pimple = new Pimple\Container();
                $this->containers[] = new Pimple\Psr11\Container($pimple);
                $this->pimple = $pimple;
            }
            $this->pimple[$id] = $value;
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
    public function offsetGet($id)
    {
        // phpcs:enable Generic.Metrics.NestingLevel

        try {
            $this->assertString($id, __METHOD__);

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
    public function offsetExists($id)
    {
        // phpcs:enable Generic.Metrics.NestingLevel

        try {
            $this->assertString($id, __METHOD__);

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
     * @param string $id
     * @return void
     */
    public function offsetUnset($id)
    {
        try {
            $this->assertString($id, __METHOD__);

            if (!$this->pimple || $this->hasCustomContainers) {
                throw new ContainerUnsetNotAllowed($id);
            }

            unset($this->pimple[$id]);
        } catch (\Throwable $throwable) {
            do_action(App::ACTION_ERROR, $throwable);

            throw $throwable;
        }
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function get($id)
    {
        return $this->offsetGet($id);
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has($id)
    {
        return $this->offsetExists($id);
    }

    /**
     * Simulating type declaration, which is not possible due to PSR-11 and \ArrayAccess interface.
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
}
