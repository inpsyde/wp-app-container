<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App\Provider;

trait AutoDiscoverIdTrait
{
    /**
     * @return string
     *
     * @suppress PhanUndeclaredProperty
     */
    public function id(): string
    {
        if (isset($this->id)) {
            return $this->id;
        }

        $class = get_called_class();

        if (defined("{$class}::ID")) {
            return constant("{$class}::ID");
        }

        static $classes = [];
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
