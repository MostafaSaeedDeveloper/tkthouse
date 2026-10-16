<?php

namespace Tests\Support;

use Illuminate\Session\Store;

/**
 * The feature-test client does not carry cookies, so every request would start a new session id. This keeps one
 * id for the "browser" (like a real session cookie) and follows it through regenerate().
 */
class PinnedSessionStore extends Store
{
    public static ?string $pin = null;

    public function setId($id)
    {
        parent::setId(static::$pin ?? $id);
    }

    public function regenerate($destroy = false)
    {
        static::$pin = null;
        $result = parent::regenerate($destroy);
        static::$pin = $this->getId();

        return $result;
    }
}
