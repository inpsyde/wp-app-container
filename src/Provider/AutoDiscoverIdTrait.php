<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider;

trait AutoDiscoverIdTrait
{
    private ?string $discoveredId = null;

    /**
     * @return string
     */
    public function id(): string
    {
        if (is_string($this->discoveredId)) {
            return $this->discoveredId;
        }

        if (is_string($this->id ?? null)) {
            $this->discoveredId = $this->id;
            /** @var string */
            return $this->discoveredId;
        }

        $class = get_called_class();

        if (defined("{$class}::ID")) {
            $byConstant = constant("{$class}::ID");
            if (is_string($byConstant)) {
                $this->discoveredId = $byConstant;
                return $this->discoveredId;
            }
        }

        /** @var int $instance */
        static $instance = 0;
        $instance++;
        $this->discoveredId = "{$class}_{$instance}";

        return $this->discoveredId;
    }
}
