<?php

namespace App\Mcp\Tools\Concerns;

use App\Services\Mcp\OutlookMcpBridge;

/**
 * Every Outlook tool previously called $this->bridge directly — the raw,
 * container-resolved OutlookMcpBridge instance, which is never scoped to a
 * user unless ->forUser() is called on it. That meant OutlookMcpBridge::
 * getClientId()/getClientSecret()/getTenantId() always fell through to the
 * global services.outlook_mcp.* config, never to whichever executive's own
 * Microsoft credentials ADR-017/CAP-017 promise are sovereign per user. This
 * trait scopes the bridge to the current session's authenticated executive
 * the same way OutlookMcpBridge::forUser() was always meant to be used.
 */
trait ScopesOutlookBridge
{
    protected function scopedBridge(OutlookMcpBridge $bridge): OutlookMcpBridge
    {
        $user = auth()->user();

        return $user ? $bridge->forUser($user) : $bridge;
    }
}
