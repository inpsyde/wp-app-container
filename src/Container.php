<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

use Pimple\Container as Pimple;
use Pimple\Exception\FrozenServiceException;
use Psr\Container\ContainerInterface;

/**
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 */
class Container extends Pimple implements ContainerInterface
{
    public const REGISTERED_PROVIDERS = 'app.container.registered-providers';
    public const APP_BOOTSTRAPPED = 'app.container.app-bootstrapped';
    public const APP_REGISTERED = 'app.container.app-registered';

    /**
     * @var SiteConfig
     */
    private $env;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var array<string,bool>
     */
    private $registeredProviders = [];

    /**
     * @var ContainerInterface[]
     */
    private $wrappedContainers = [];

    /**
     * @param SiteConfig $env
     * @param Context $context
     * @param ContainerInterface ...$wrappedContainers
     */
    public function __construct(
        SiteConfig $env,
        Context $context,
        ContainerInterface ...$wrappedContainers
    ) {

        parent::__construct();
        $this->env = $env;
        $this->context = $context;
        $this->wrappedContainers = $wrappedContainers;
    }

    /**
     * @return SiteConfig
     */
    public function env(): SiteConfig
    {
        return $this->env;
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
    final public function pushContainer(ContainerInterface $container): Container
    {
        $this->wrappedContainers[] = $container;

        return $this;
    }

    /**
     * @param string $id
     * @param mixed $value
     * @return void
     */
    final public function offsetSet($id, $value)
    {
        try {
            $this->assertString($id, __METHOD__);
            if (!$this->maybeSaveRegistered($id, $value)) {
                parent::offsetSet($id, $value);
            }
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
            if (parent::offsetExists($id)) {
                return parent::offsetGet($id);
            }

            foreach ($this->wrappedContainers as $container) {
                if ($container->has($id)) {
                    return $container->get($id);
                }
            }

            return parent::offsetGet($id);
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
            if (parent::offsetExists($id)) {
                return true;
            }

            foreach ($this->wrappedContainers as $container) {
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

            parent::offsetUnset($id);
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
     * @param string $providerId
     * @return bool
     */
    final public function hasProvider(string $providerId): bool
    {
        return $this->registeredProviders[$providerId] ?? false;
    }

    /**
     * @param string $id
     * @param $value
     * @return bool
     */
    private function maybeSaveRegistered(string $id, $value): bool
    {
        if ($id !== self::REGISTERED_PROVIDERS) {
            return false;
        }

        if ($this->has(self::APP_REGISTERED)) {
            throw new FrozenServiceException($id);
        }

        if (!is_array($value)) {
            throw new \TypeError(
                sprintf(
                    'Registered provider must be an array, %s given.',
                    gettype($value)
                )
            );
        }

        $this->registeredProviders = $value;

        return true;
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
