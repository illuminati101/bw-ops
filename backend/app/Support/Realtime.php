<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/** Broadcast without ever breaking the request if the websocket server is down (apps fall back to polling). */
class Realtime
{
    public static function send(object $event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            Log::debug('Realtime broadcast skipped: '.$e->getMessage());
        }
    }

    /** How browsers and apps should connect to Reverb, or null when realtime is off (they poll instead). */
    public static function publicConfig(): ?array
    {
        $ws = config('bwops.integrations.public_ws');
        if (config('broadcasting.default') !== 'reverb' || blank($ws['key'])) {
            return null;
        }
        $request = request();
        $scheme = $ws['scheme'] ?: ($request->secure() ? 'https' : 'http');
        // Behind a tunnel with no separate websocket address, use the page's own host on 443.
        $port = $ws['host'] ? (int) $ws['port'] : ($request->secure() ? 443 : (int) $ws['port']);

        return ['key' => $ws['key'], 'host' => $ws['host'] ?: $request->getHost(), 'port' => $port, 'scheme' => $scheme];
    }
}
