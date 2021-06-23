<?php

declare(strict_types=1);

namespace Inpsyde\App\Config;

final class Location
{
    /**
     * @var string
     */
    private $dir;

    /**
     * @var string|null
     */
    private $url;

    /**
     * @param Location $location
     * @param string $path
     * @return Location
     */
    public static function compose(Location $location, string $path): Location
    {
        $path = ltrim($path, '/');

        return new self(
            $location->dir() . $path,
            ($location->url() === null) ? null : $location->url() . $path
        );
    }

    /**
     * @param string $dir
     * @param string|null $url
     * @return Location
     */
    public static function new(string $dir, ?string $url): Location
    {
        return new self($dir, $url);
    }

    /**
     * @param string $dir
     * @param ?string $url
     */
    private function __construct(string $dir, ?string $url)
    {
        $this->dir = untrailingslashit(wp_normalize_path($dir));
        $this->url = is_string($url) ? untrailingslashit(set_url_scheme($url)) : null;
    }

    /**
     * @return string
     */
    public function dir(): string
    {
        return $this->dir;
    }

    /**
     * @return string|null
     */
    public function url(): ?string
    {
        return $this->url;
    }
}
