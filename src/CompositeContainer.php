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
     * @var list<ContainerInterface>
     */
    private $containers = [];

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

        $instance = new static(
            $context ?? $container->context(),
            $config ?? $container->config()
        );

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
        $this->containers[] = $container;

        return $this;
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function get(string $id)
    {
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
        return $this->findContainerFor($id) !== null;
    }

    /**
     * @param string $id
     * @return ContainerInterface|null
     */
    private function findContainerFor(string $id): ?ContainerInterface
    {
        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return $container;
            }
        }

        return null;
    }
}
