<?php

declare(strict_types=1);

namespace Inpsyde\App;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class CompositeContainer implements ContainerInterface
{
    /**
     * @var list<ContainerInterface>
     */
    private $containers = [];

    /**
     * @param ContainerInterface ...$containers
     * @return CompositeContainer
     */
    public static function new(ContainerInterface ...$containers): CompositeContainer
    {
        return new self(...$containers);
    }

    /**
     * @param ContainerInterface ...$containers
     */
    private function __construct(ContainerInterface ...$containers)
    {
        /** @var list<ContainerInterface> $containers */
        $this->containers = $containers;
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
            throw new class ($error) extends \Exception implements NotFoundExceptionInterface {
            };
        }

        return $container->get($id);
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has(string $id)
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
