<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Inpsyde\App\Config\Config;
use Inpsyde\WpContext;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class CompositeContainer implements ContainerInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var WpContext
     */
    private $context;

    /**
     * @var array<string, ContainerInterface>
     */
    public $containers;

    /**
     * @var array<string, string>
     */
    private $map = [];

    /**
     * @var string|null
     */
    private $checking = null;

    /**
     * @param CompositeContainer $container
     * @param WpContext|null $context
     * @param Config|null $config
     * @return CompositeContainer
     */
    public static function newFromExisting(
        CompositeContainer $container,
        WpContext $context = null,
        Config $config = null
    ): CompositeContainer {

        $instance = new static($context ?? $container->context(), $config ?? $container->config());
        foreach ($container->containers as $container) {
            $instance->addContainer($container);
        }

        return $instance;
    }

    /**
     * @param WpContext $context
     * @param Config $config
     * @param ContainerInterface ...$containers
     * @return CompositeContainer
     */
    public static function new(
        WpContext $context,
        Config $config,
        ContainerInterface ...$containers
    ): CompositeContainer {

        $instance = new static($context, $config);
        foreach ($containers as $container) {
            $instance->addContainer($container);
        }

        return $instance;
    }

    /**
     * @param WpContext $context
     * @param Config $config
     */
    private function __construct(WpContext $context, Config $config)
    {
        $this->context = $context;
        $this->config = $config;
        $this->containers = [];
    }

    /**
     * @return WpContext
     */
    public function context(): WpContext
    {
        return $this->context;
    }

    /**
     * @return Config
     */
    public function config(): Config
    {
        return $this->config;
    }

    /**
     * @param ContainerInterface $container
     * @return static
     */
    public function addContainer(ContainerInterface $container): CompositeContainer
    {
        $hash = spl_object_hash($container);
        if (($container !== $this) && !isset($this->containers[$hash])) {
            $this->containers[$hash] = $container;
        }

        return $this;
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function get(string $id)
    {
        if ($id === Config::class) {
            return $this->config();
        }

        if ($id === WpContext::class) {
            return $this->context();
        }

        $container = $this->findContainerFor($id);
        if (!$container) {
            $error = "Service {$id} not found.";
            throw new class ($error) extends \Exception implements NotFoundExceptionInterface
            {
            };
        }

        return $container->get($id);
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        if (in_array($id, [Config::class, WpContext::class], true)) {
            return true;
        }

        if ($this->checking === $id) {
            return false;
        }

        $this->checking = $id;
        $has = $this->findContainerFor($id) !== null;
        $this->checking = null;

        return $has;
    }

    /**
     * @param ContainerInterface $container
     * @return bool
     */
    public function hasContainer(ContainerInterface $container): bool
    {
        return ($container === $this) || isset($this->containers[spl_object_hash($container)]);
    }

    /**
     * @param string $id
     * @return ContainerInterface|null
     */
    private function findContainerFor(string $id): ?ContainerInterface
    {
        $hash = $this->map[$id] ?? null;
        if ($hash) {
            return $this->containers[$hash];
        }

        foreach ($this->containers as $hash => $container) {
            if ($container->has($id)) {
                $this->map[$id] = $hash;

                return $container;
            }
        }

        return null;
    }
}
