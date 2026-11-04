<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

use Beziworld\Cpanel\Data\UserSession;

/**
 * Single sign-on: one-time login URLs to cPanel, WHM or Webmail (WHM create_user_session).
 */
final class Sessions extends Resource
{
    /**
     * Creates a one-time login URL for an account.
     *
     * @param string $service One of: cpaneld, whostmgrd, webmaild.
     * @param string|null $app Interface to open after login, e.g. "Email_Accounts" or "FileManager_Home".
     */
    public function create(string $username, string $service = 'cpaneld', ?string $app = null, ?string $locale = null): UserSession
    {
        return UserSession::fromArray($this->connection->whm('create_user_session', [
            'user' => $username,
            'service' => $service,
            'app' => $app,
            'locale' => $locale,
        ], write: true));
    }

    /**
     * Shortcut returning only the one-time cPanel login URL.
     */
    public function cpanelUrl(string $username, ?string $app = null): string
    {
        return $this->create($username, 'cpaneld', $app)->url;
    }

    /**
     * One-time Webmail login URL for an e-mail account (username is the address).
     */
    public function webmailUrl(string $email): string
    {
        return $this->create($email, 'webmaild')->url;
    }
}
