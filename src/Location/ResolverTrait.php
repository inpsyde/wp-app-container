<?php declare(strict_types=1); # -*- coding: utf-8 -*-

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
     * @param string $path
     * @return string|null
     */
    public function pluginsDir(string $path = '/'): ?string
    {
        return $this->resolver()->resolveDir(Locations::PLUGINS, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function pluginsUrl(string $path = '/'): ?string
    {
        return $this->resolver()->resolveUrl(Locations::PLUGINS, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function muPluginsDir(string $path = '/'): ?string
    {
        return $this->resolver()->resolveDir(Locations::MU_PLUGINS, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function muPluginsUrl(string $path = '/'): ?string
    {
        return $this->resolver()->resolveUrl(Locations::MU_PLUGINS, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function themesDir(string $path = '/'): ?string
    {
        return $this->resolver()->resolveDir(Locations::THEMES, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function themesUrl(string $path = '/'): ?string
    {
        return $this->resolver()->resolveUrl(Locations::THEMES, $path);
    }

    /**
     * @return string|null
     */
    public function languagesDir(): ?string
    {
        return $this->resolver()->resolveDir(Locations::LANGUAGES, '/');
    }

    /**
     * @return string|null
     */
    public function languagesUrl(): ?string
    {
        return $this->resolver()->resolveUrl(Locations::LANGUAGES, '/');
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function contentDir(string $path = '/'): ?string
    {
        return $this->resolver()->resolveDir(Locations::CONTENT, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function contentUrl(string $path = '/'): ?string
    {
        return $this->resolver()->resolveUrl(Locations::CONTENT, $path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function vendorDir(string $path = '/'): ?string
    {
        return $this->resolver()->resolveDir(Locations::VENDOR, $path);
    }

    /**
     * @return string|null
     */
    public function vendorUrl(string $path = '/'): ?string
    {
        return $this->resolver()->resolveUrl(Locations::VENDOR, $path);
    }

    /**
     * @return string|null
     */
    public function rootDir(): ?string
    {
        return $this->resolver()->resolveDir(Locations::VENDOR, '/');
    }

    /**
     * @return string|null
     */
    public function rootUrl(): ?string
    {
        return $this->resolver()->resolveUrl(Locations::VENDOR, '/');
    }
}
