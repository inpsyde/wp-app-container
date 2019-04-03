<?php declare(strict_types=1); # -*- coding: utf-8 -*-

namespace Inpsyde\App;

final class Context implements \JsonSerializable
{
    public const CORE = 'core';
    public const FRONTOFFICE = 'frontoffice';
    public const BACKOFFICE = 'backoffice';
    public const AJAX = 'ajax';
    public const REST = 'rest';
    public const CRON = 'cron';
    public const LOGIN = 'login';
    public const CLI = 'wpcli';

    /**
     * @var array
     */
    private $data;

    /**
     * @return Context
     */
    public static function create(): Context
    {
        $isCore = defined('ABSPATH');
        $isAjax = $isCore ? wp_doing_ajax() : false;
        $isAdmin = $isCore ? is_admin() && !$isAjax : false;
        $isCron = $isCore ? wp_doing_cron() : false;
        $isRest = $isCore ? static::isRestRequest() : false;
        $isLogin = $isCore ? static::isLoginRequest() : false;
        $isCli = defined('WP_CLI');
        $isFront = !$isAdmin && !$isAjax && !$isRest && !$isCron && !$isLogin && !$isCli;

        return new static(
            [
                self::CORE => $isCore,
                self::FRONTOFFICE => $isCore && $isFront,
                self::BACKOFFICE => $isAdmin,
                self::LOGIN => $isLogin,
                self::AJAX => $isAjax,
                self::REST => $isRest,
                self::CRON => $isCron,
                self::CLI => $isCli,
            ]
        );
    }

    /**
     * @return bool
     */
    private static function isRestRequest(): bool
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        // This is needed because, if called early, global $wp_rewrite is not defined but required
        // by get_rest_url(). WP will reuse what we set here, or in worst case will replace, but no
        // consequences for us in any case.
        if (get_option('permalink_structure') && empty($GLOBALS['wp_rewrite'])) {
            $GLOBALS['wp_rewrite'] = new \WP_Rewrite();
        }

        $currentUrl = rtrim(set_url_scheme(add_query_arg([])), '/');
        $restUrl = rtrim(set_url_scheme(get_rest_url()), '/');

        return strpos($currentUrl, $restUrl) === 0;
    }

    /**
     * @return bool
     */
    private static function isLoginRequest(): bool
    {
        $pageNow = $GLOBALS['pagenow'] ?? '';

        return $pageNow && (basename($pageNow) === 'wp-login.php');
    }

    /**
     * @param array $data
     */
    private function __construct(array $data)
    {
        $this->data = $data;

        add_action(
            'login_init',
            /**
             * @suppress PhanUnreferencedClosure
             */
            function () {
                $this->data[self::LOGIN] = true;
                $this->data[self::FRONTOFFICE] = false;
            }
        );

        add_action(
            'rest_api_init',
            /**
             * @suppress PhanUnreferencedClosure
             */
            function () {
                $this->data[self::REST] = true;
                $this->data[self::FRONTOFFICE] = false;
            }
        );
    }

    /**
     * @param string $context
     * @param string ...$contexts
     * @return bool
     */
    public function is(string $context, string ...$contexts): bool
    {
        array_unshift($contexts, $context);

        foreach ($contexts as $context) {
            if (($this->data[$context] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isCore(): bool
    {
        return $this->is(self::CORE);
    }

    /**
     * @return bool
     */
    public function isFrontoffice(): bool
    {
        return $this->is(self::FRONTOFFICE);
    }

    /**
     * @return bool
     */
    public function isBackoffice(): bool
    {
        return $this->is(self::BACKOFFICE);
    }

    /**
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->is(self::AJAX);
    }

    /**
     * @return bool
     */
    public function isLogin(): bool
    {
        return $this->is(self::LOGIN);
    }

    /**
     * @return bool
     */
    public function isRest(): bool
    {
        return $this->is(self::REST);
    }

    /**
     * @return bool
     */
    public function isCron(): bool
    {
        return $this->is(self::CRON);
    }

    /**
     * @return bool
     */
    public function isWpCli(): bool
    {
        return $this->is(self::CLI);
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->data;
    }
}
