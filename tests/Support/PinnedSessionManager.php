<?php

namespace Tests\Support;

use Illuminate\Session\SessionManager;

class PinnedSessionManager extends SessionManager
{
    protected function buildSession($handler)
    {
        return new PinnedSessionStore($this->config->get('session.cookie'), $handler, null, $this->config->get('session.serialization', 'php'));
    }
}
