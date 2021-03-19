<?php

namespace Inpsyde\App;

interface ContextInterface extends \JsonSerializable
{
    /**
     * @param string $context
     * @param string ...$contexts
     * @return bool
     */
    public function is(string $context, string ...$contexts): bool;

    /**
     * @return bool
     */
    public function isCore(): bool;

    /**
     * @return bool
     */
    public function isFrontoffice(): bool;

    /**
     * @return bool
     */
    public function isBackoffice(): bool;

    /**
     * @return bool
     */
    public function isAjax(): bool;

    /**
     * @return bool
     */
    public function isLogin(): bool;

    /**
     * @return bool
     */
    public function isRest(): bool;

    /**
     * @return bool
     */
    public function isCron(): bool;

    /**
     * @return bool
     */
    public function isWpCli(): bool;

    /**
     * @return bool
     */
    public function isXmlRpc(): bool;

    /**
     * @return bool
     */
    public function isInstalling(): bool;
}
