<?php

namespace App\Services;

use Minishlink\WebPush\{Subscription, VAPID, WebPush};

class PortalPushTransport
{
    public function keys(): ?array
    {
        $path = config('portal_push.key_file');
        if (!config('portal_push.enabled') || !is_file($path)) return null;
        $keys = json_decode(file_get_contents($path), true);
        return isset($keys['publicKey'], $keys['privateKey']) ? $keys : null;
    }

    public function setup(): void
    {
        $path = config('portal_push.key_file');
        if (is_file($path)) {
            if (!$this->keys()) throw new \RuntimeException('Existing push keys are invalid or push is disabled; keys were preserved.');
            return;
        }
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        $keys = VAPID::createVapidKeys();
        $handle = fopen($path, 'x'); // never rotate a live subscription's key
        if (!$handle) throw new \RuntimeException('Could not create private push key file.');
        chmod($path, 0600);
        try { fwrite($handle, json_encode($keys, JSON_THROW_ON_ERROR)); }
        finally { fclose($handle); }
    }

    public function send(array $subscription, array $payload): string
    {
        $keys = $this->keys();
        if (!$keys) return 'retry';
        $push = $this->client($keys);
        // Browser toJSON() omits the encoding; explicitly use RFC 8291 (including Safari).
        $target = Subscription::create(array_merge($subscription, ['contentEncoding' => 'aes128gcm']));
        $report = $push->sendOneNotification($target, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        // Provider acceptance is not proof that a patient received or read it.
        if ($report->isSuccess()) return 'accepted';
        if ($report->isSubscriptionExpired()) return 'expired';
        return 'retry';
    }

    protected function client(array $keys): WebPush
    {
        return new WebPush(['VAPID' => $keys + ['subject' => config('portal_push.subject')]], ['TTL' => 3600, 'urgency' => 'normal'], 8,
            ['allow_redirects' => false]);
    }
}
