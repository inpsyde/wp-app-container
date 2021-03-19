<?php

declare(strict_types=1);

namespace Inpsyde\App;

final class Context implements ContextInterface
{
    public const AJAX = 'ajax';
    public const BACKOFFICE = 'backoffice';
    public const CLI = 'wpcli';
    public const CORE = 'core';
    public const CRON = 'cron';
    public const FRONTOFFICE = 'frontoffice';
    public const INSTALLING = 'installing';
    public const LOGIN = 'login';
    public const REST = 'rest';
    public const XML_RPC = 'xml-rpc';

    /**
     * @var array
     */
    private $data;

    /**
     * @return Context
     */
    public static function create(): Context
    {
        $installing = defined('WP_INSTALLING') && WP_INSTALLING;
        $xmlRpc = defined('XMLRPC_REQUEST') && XMLRPC_REQUEST;
        $isCore = defined('ABSPATH');
        $isCli = defined('WP_CLI');
        $notInstalling = $isCore && !$installing;
        $isAjax = $notInstalling ? wp_doing_ajax() : false;
        $isAdmin = $notInstalling ? is_admin() && !$isAjax : false;
        $isCron = $notInstalling ? wp_doing_cron() : false;

        $undetermined = $notInstalling && !$isAdmin && !$isCron && !$isCli && !$xmlRpc && !$isAjax;

        $isRest = $undetermined ? static::isRestRequest() : false;
        $isLogin = ($undetermined && !$isRest) ? static::isLoginRequest() : false;

        $isFront = $undetermined && !$isRest && !$isLogin;

        // Note that when installing **only** `INSTALLING` will be true, not even `CORE`.
        // This is done to do as less as possible during installation, when most of WP does not act
        // as expected.

        return new static(
            [
                self::CORE => ($isCore || $xmlRpc) && !$installing,
                self::FRONTOFFICE => $isFront,
                self::BACKOFFICE => $isAdmin,
                self::LOGIN => $isLogin,
                self::AJAX => $isAjax,
                self::REST => $isRest,
                self::CRON => $isCron,
                self::CLI => $isCli,
                self::XML_RPC => $xmlRpc,
                self::INSTALLING => $installing,
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

        $currentUrl = set_url_scheme(add_query_arg([]));
        $restUrl = set_url_scheme(get_rest_url());
        $currentPath = trim((string)parse_url((string)$currentUrl, PHP_URL_PATH), '/') . '/';
        $restPath = trim((string)parse_url((string)$restUrl, PHP_URL_PATH), '/') . '/';

        return strpos($currentPath, $restPath) === 0;
    }

    /**
     * @return bool
     */
    private static function isLoginRequest(): bool
    {
        if (!empty($_REQUEST['interim-login'])) { // phpcs:ignore
            return true;
        }

        $pageNow = (string)($GLOBALS['pagenow'] ?? '');
        if ($pageNow && (basename($pageNow) === 'wp-login.php')) {
            return true;
        }

        $url = home_url((string)parse_url(add_query_arg([]), PHP_URL_PATH));

        return rtrim($url, '/') === rtrim(wp_login_url(), '/');
    }

    /**
     * @param array $data
     */
    private function __construct(array $data)
    {
        $this->data = $data;

        add_action(
            'login_init',
            function () {
                $this->data[self::LOGIN] = true;
                $this->data[self::REST] = false;
                $this->data[self::FRONTOFFICE] = false;
            }
        );

        add_action(
            'rest_api_init',
            function () {
                $this->data[self::REST] = true;
                $this->data[self::LOGIN] = false;
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
     * @return bool
     */
    public function isXmlRpc(): bool
    {
        return $this->is(self::XML_RPC);
    }

    /**
     * @return bool
     */
    public function isInstalling(): bool
    {
        return $this->is(self::INSTALLING);
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->data;
    }
}
