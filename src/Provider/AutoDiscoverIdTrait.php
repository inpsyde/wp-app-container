<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Provider;

trait AutoDiscoverIdTrait
{
    /**
     * @return string
     */
    public function id(): string
    {
        if (isset($this->id) && is_string($this->id)) {
            return $this->id;
        }

        $class = get_called_class();

        if (defined("{$class}::ID")) {
            $byConstant = constant("{$class}::ID");
            if (is_string($byConstant)) {
                return $byConstant;
            }
        }

        /** @var array<string, int> $classes */
        static $classes = [];

        /** @var array<string, string> $hashes */
        static $hashes = [];

        isset($classes[$class]) or $classes[$class] = 0;

        $hash = spl_object_hash($this);

        if (isset($hashes[$hash])) {
            return $hashes[$hash];
        }

        $classes[$class]++;

        $id = $class;
        if ($classes[$class] > 1) {
            $id .= "_{$classes[$class]}";
        }

        $hashes[$hash] = $id;

        return $id;
    }
}
