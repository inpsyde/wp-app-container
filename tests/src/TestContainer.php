<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class TestContainer implements ContainerInterface
{
    /**
     * @var array
     */
    private $things;

    /**
     * @param array $things
     */
    public function __construct(array $things = [])
    {
        $this->things = $things;
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new class ($id) extends \Exception implements NotFoundExceptionInterface {
            };
        }

        return $this->things[$id];
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->things);
    }
}
