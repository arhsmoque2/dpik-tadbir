<?php

namespace App\Mcp\Tools\Concerns;

use App\Services\Mail\MailBridge;

/**
 * Every mail tool calls $this->bridge — the raw, container-resolved
 * MailBridge instance, which is never scoped to a user unless ->forUser()
 * is called on it. This trait scopes the bridge to the current session's
 * authenticated executive, so it reads/sends against that executive's own
 * sovereign IMAP/SMTP credentials rather than falling through to nothing.
 */
trait ScopesMailBridge
{
    protected function scopedBridge(MailBridge $bridge): MailBridge
    {
        $user = auth()->user();

        return $user ? $bridge->forUser($user) : $bridge;
    }
}
