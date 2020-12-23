<?php

declare(strict_types=1);

namespace Inpsyde\App\Location;

trait ResolverTrait
{
    /**
     * @var LocationResolver|null
     */
    private $resolver;

    /**
     * @param LocationResolver $resolver
     */
    private function injectResolver(LocationResolver $resolver): void
    {
        $this->resolver = $resolver;
    }

    /**
     * @return LocationResolver
     */
    private function resolver(): LocationResolver
    {
        if (!$this->resolver instanceof LocationResolver) {
            throw new \LogicException(sprintf('No location resolver found for %s.', __CLASS__));
        }

        return $this->resolver;
    }

    /**
     * @param string $name
     * @param string $path
     * @return string|null
     */
    public function resolveDir(string $name, string $path = '/'): ?string
    {
        return $this->resolver()->resolveDir($name, $path);
    }

    /**
     * @param string $name
     * @param string $path
     * @return string|null
     */
    public function resolveUrl(string $name, string $path = '/'): ?string
    {
        return $this->resolver()->resolveUrl($name, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function pluginsDir(string $path = '/'): ?string
    {
        return $this->resolveDir(Locations::PLUGINS, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function pluginsUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(Locations::PLUGINS, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function muPluginsDir(string $path = '/'): ?string
    {
        return $this->resolveDir(Locations::MU_PLUGINS, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function muPluginsUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(Locations::MU_PLUGINS, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function themesDir(string $path = '/'): ?string
    {
        return $this->resolveDir(Locations::THEMES, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function themesUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(Locations::THEMES, $path);
    }

    /**
     * @return string|null
     */
    public function languagesDir(): ?string
    {
        return $this->resolveDir(Locations::LANGUAGES, '/');
    }

    /**
     * @return string|null
     */
    public function languagesUrl(): ?string
    {
        return $this->resolveUrl(Locations::LANGUAGES, '/');
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function contentDir(string $path = '/'): ?string
    {
        return $this->resolveDir(Locations::CONTENT, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function contentUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(Locations::CONTENT, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function vendorDir(string $path = '/'): ?string
    {
        return $this->resolveDir(Locations::VENDOR, $path);
    }

    /**
     * @return string|null
     */
    public function vendorUrl(string $path = '/'): ?string
    {
        return $this->resolveUrl(Locations::VENDOR, $path);
    }

    /**
     * @return string|null
     */
    public function rootDir(): ?string
    {
        return $this->resolveDir(Locations::ROOT, '/');
    }

    /**
     * @return string|null
     */
    public function rootUrl(): ?string
    {
        return $this->resolveUrl(Locations::ROOT, '/');
    }
}
