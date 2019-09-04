<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

class VipPaths extends BasePaths
{

    public const MU_PLUGINS_DIR = 'client-mu-plugins';
    public const CONFIG_DIR = 'vip-config';
    public const IMAGES_DIR = 'images';
    public const PRIVATE_DIR = 'private';

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @return string
     */
    public function configDir(): string
    {
        return $this->dir(self::CONFIG_DIR);
    }

    /**
     * @param string $subDir
     *
     * @return string
     */
    public function privateDir(string $subDir = ''): string
    {
        return $this->dir(self::PRIVATE_DIR, $subDir);
    }

    /**
     * @return string
     */
    public function imagesDir(): string
    {
        return $this->dir(self::IMAGES_DIR);
    }

    /**
     * @return string
     */
    public function imagesUrl(): string
    {
        return $this->url(self::IMAGES_DIR);
    }
}
