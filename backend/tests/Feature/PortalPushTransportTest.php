<?php

namespace Tests\Feature;

use App\Services\PortalPushTransport;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\{VAPID, WebPush};
use Tests\TestCase;

class PortalPushTransportTest extends TestCase
{
    public function test_persistent_keys_and_real_encrypted_web_push_requests(): void
    {
        $path = sys_get_temp_dir().'/portal-keys-'.bin2hex(random_bytes(8)).'.json';
        config(['portal_push.enabled' => true, 'portal_push.key_file' => $path, 'portal_push.subject' => 'https://lab.example.test']);
        $history = [];
        $handler = HandlerStack::create(new MockHandler([new Response(201), new Response(410), new Response(503)]));
        $handler->push(Middleware::history($history));
        $transport = new class($handler) extends PortalPushTransport {
            public function __construct(private HandlerStack $handler) {}
            protected function client(array $keys): WebPush
            {
                return new WebPush(['VAPID' => $keys + ['subject' => config('portal_push.subject')]], ['TTL' => 3600], 8,
                    ['handler' => $this->handler, 'allow_redirects' => false]);
            }
        };
        try {
            $transport->setup(); $keys = $transport->keys();
            $transport->setup(); $this->assertSame($keys, $transport->keys(), 'deployment must not rotate existing keys');
            $this->assertSame(0600, fileperms($path) & 0777);
            $client = VAPID::createVapidKeys();
            $subscription = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/synthetic-test',
                'keys' => ['p256dh' => $client['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')]];
            $payload = ['title' => 'Ready', 'body' => 'Open your portal', 'url' => 'https://lab.example.test/portal/'.str_repeat('a', 48)];
            $this->assertSame('accepted', $transport->send($subscription, $payload));
            $this->assertSame('expired', $transport->send($subscription, $payload));
            $this->assertSame('retry', $transport->send($subscription, $payload));
            $request = $history[0]['request'];
            $this->assertSame('aes128gcm', $request->getHeaderLine('Content-Encoding'));
            $this->assertStringStartsWith('vapid ', $request->getHeaderLine('Authorization'));
            $this->assertStringNotContainsString($payload['url'], (string) $request->getBody());
            $this->assertFalse($history[0]['options']['allow_redirects']);
        } finally { if (is_file($path)) unlink($path); }
    }
}
