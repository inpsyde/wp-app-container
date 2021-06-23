<?php

declare(strict_types=1);

namespace Inpsyde\App\Provider\Capabilities;

trait AutoDiscoverIdTrait
{
    /**
     * When an `$id` non empty string property is available, that is returned.
     * Otherwise, an ID is calculated using the FQ class name. And in the case multiple instances
     * exists for that class, a numeric suffix is added to make sure ID is different per instance,
     * not per class.
     *
     * @return string
     */
    public function id(): string
    {
        if (isset($this->id) && is_string($this->id) && $this->id) {
            return $this->id;
        }

        $class = get_called_class();
        if (preg_match('~^class@anonymous(.+)~', $class, $match)) {
            $class = 'class@anonymous';
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
        if ($classes[$class] > 1 || ($class === 'class@anonymous')) {
            $id .= "_{$hash}";
        }

        $hashes[$hash] = $id;

        return $id;
    }
}
