<?php

namespace Tests\Feature;

use App\Models\{Invoice, LabSetting, Patient, PortalAccessToken, PortalNotification, PortalPushDelivery, PortalPushSubscription, User};
use App\Services\{PortalNotifications, PortalPushTransport};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PortalNotificationTest extends TestCase
{
    use DatabaseMigrations;
    private array $sent = [];
    private string $sendStatus = 'accepted';

    protected function setUp(): void
    {
        parent::setUp();
        $transport = \Mockery::mock(PortalPushTransport::class);
        $transport->shouldReceive('keys')->andReturn(['publicKey' => 'public-test-key', 'privateKey' => 'DO_NOT_EXPOSE']);
        $transport->shouldReceive('send')->andReturnUsing(function ($subscription, $payload) {
            $this->sent[] = $payload;
            return $this->sendStatus;
        });
        $this->app->instance(PortalPushTransport::class, $transport);
        config(['app.frontend_url' => 'https://lab.example.test']);
    }

    private function fixture(): array
    {
        $lab = User::create(['name' => 'Sample Lab', 'email' => uniqid().'@example.test', 'password' => 'test-password-123', 'role_id' => 2]);
        $patient = Patient::create(['user_id' => $lab->id, 'creator_id' => $lab->id, 'code' => uniqid('P')]);
        $access = PortalAccessToken::create(['patient_id_fk' => $patient->id, 'token' => PortalAccessToken::generatePlainToken(), 'expires_at' => now()->addDay()]);
        return [$lab, $patient, $access, '/api/portal/'.$access->token];
    }

    private function subscription(bool $results = true, bool $offers = false): array
    {
        $encode = fn ($value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        return ['subscription' => ['endpoint' => 'https://fcm.googleapis.com/fcm/send/synthetic-browser',
            'keys' => ['p256dh' => $encode("\x04".str_repeat('a', 64)), 'auth' => $encode(str_repeat('b', 16))]],
            'results_enabled' => $results, 'offers_enabled' => $offers];
    }

    private function withPortalEntry(callable $check): void
    {
        $original = public_path();
        $directory = sys_get_temp_dir().'/portal-shell-'.bin2hex(random_bytes(8));
        File::makeDirectory($directory);
        // Exercise the real HTML template, including Arabic, JSON-LD, icons
        // and the module entry. Add representative Vite production assets.
        $entry = str_replace('</head>', '<link rel="modulepreload" crossorigin href="/assets/vue-hash.js"><link rel="stylesheet" crossorigin href="/assets/app-hash.css"><script type="module" crossorigin src="/assets/app-hash.js"></script></head>', file_get_contents(base_path('../frontend/index.html')));
        File::put($directory.'/index.html', $entry);
        $this->app->usePublicPath($directory);
        try { $check($entry); }
        finally { $this->app->usePublicPath($original); File::deleteDirectory($directory); }
    }

    public function test_initial_portal_html_selects_patient_app_before_javascript_and_preserves_build_assets(): void
    {
        [, $patient, $access, $api] = $this->fixture();
        [, , $other] = $this->fixture();
        $this->withPortalEntry(function ($original) use ($patient, $access, $api, $other) {
            $response = $this->get('/portal/'.$access->token.'?form=1&report=14')->assertOk()
                ->assertHeader('Content-Type', 'text/html; charset=UTF-8')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertDontSee('href="/manifest.json"', false)->assertDontSee($patient->code);
            $this->assertTrue($response->headers->hasCacheControlDirective('private'));
            $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
            $this->assertFalse($response->headers->has('Location'));
            $document = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try { $document->loadHTML($response->getContent()); }
            finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//link[@rel="manifest"]')->length);
            $this->assertSame($api.'/manifest.webmanifest', $xpath->evaluate('string(//link[@rel="manifest"]/@href)'));
            $this->assertSame('/portal/'.$access->token, $xpath->evaluate('string(//link[@rel="canonical"]/@href)'));
            $this->assertSame('yes', $xpath->evaluate('string(//meta[@name="apple-mobile-web-app-capable"]/@content)'));
            $this->assertSame('بوابة المريض', $xpath->evaluate('string(//meta[@name="apple-mobile-web-app-title"]/@content)'));
            $this->assertSame('بوابة المريض', $xpath->evaluate('string(//title)'));
            $this->assertSame(1, $xpath->query('//script[@type="module" and @src="/assets/app-hash.js"]')->length);
            $this->assertSame(1, $xpath->query('//link[@rel="stylesheet" and @href="/assets/app-hash.css"]')->length);
            $this->assertSame(1, $xpath->query('//link[@rel="modulepreload" and @href="/assets/vue-hash.js"]')->length);
            $this->assertSame(1, $xpath->query('//link[@rel="apple-touch-icon"]')->length);
            $this->assertSame(1, $xpath->query('//div[@id="app"]')->length);
            $this->assertSame(0, $xpath->query('//script[@type="application/ld+json"]')->length);
            $this->get('/portal/'.$other->token)->assertOk()->assertSee('/api/portal/'.$other->token.'/manifest.webmanifest', false)
                ->assertDontSee($access->token);
            $manifest = $this->getJson($api.'/manifest.webmanifest')->assertOk()->assertJsonPath('display', 'standalone')->json();
            $this->assertSame('https://lab.example.test/portal/'.$access->token, $manifest['start_url']);
            $this->assertSame($manifest['start_url'], $manifest['id']);
            $this->get(parse_url($manifest['start_url'], PHP_URL_PATH))->assertOk()->assertDontSee('href="/manifest.json"', false);
            $this->assertSame($original, File::get(public_path('index.html')), 'Portal rendering must not rewrite the shared staff entry.');
        });
    }

    public function test_portal_shell_does_not_bypass_otp_expiry_or_redirect_to_the_staff_site(): void
    {
        [$lab, , $access, $api] = $this->fixture();
        LabSetting::create(['lab_id_fk' => $lab->id, 'loyalty_config' => ['require_otp' => true]]);
        $this->withPortalEntry(function () use ($access, $api) {
            $this->get('/portal/'.$access->token)->assertOk()->assertDontSee('href="/manifest.json"', false);
            $this->getJson($api.'/manifest.webmanifest')->assertForbidden();
            $this->getJson($api.'/app-config')->assertForbidden();
            $access->update(['verified_at' => now()]);
            $this->getJson($api.'/manifest.webmanifest')->assertOk();
            $access->update(['expires_at' => now()->subMinute()]);
            $this->get('/portal/'.$access->token)->assertOk()->assertDontSee('href="/manifest.json"', false);
            $this->getJson($api.'/manifest.webmanifest')->assertNotFound();
            $this->getJson($api)->assertNotFound();
            $this->get('/portal/not-a-valid-token')->assertNotFound();
            $this->get('/portal/sw.js')->assertNotFound(); // Served as a static file by Hostinger.
            File::delete(public_path('index.html'));
            $this->get('/portal/'.$access->token)->assertStatus(503);
        });
    }

    public function test_manifest_and_settings_require_valid_verified_access_and_expose_only_public_key(): void
    {
        [$lab, , $access, $url] = $this->fixture();
        $this->getJson($url.'/app-config')->assertOk()->assertJsonPath('public_key', 'public-test-key')->assertDontSee('DO_NOT_EXPOSE');
        $manifest = $this->getJson($url.'/manifest.webmanifest')->assertOk()->assertJsonPath('scope', 'https://lab.example.test/portal/')
            ->assertJsonPath('start_url', 'https://lab.example.test/portal/'.$access->token)
            ->assertHeader('Content-Type', 'application/manifest+json');
        $this->assertTrue($manifest->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($manifest->headers->hasCacheControlDirective('private'));
        LabSetting::create(['lab_id_fk' => $lab->id, 'loyalty_config' => ['require_otp' => true]]);
        $this->getJson($url.'/app-config')->assertForbidden();
        $this->postJson($url.'/push/subscribe', $this->subscription())->assertForbidden();
        $this->getJson($url.'/notifications')->assertForbidden();
        $access->update(['verified_at' => now()]);
        $this->getJson($url.'/app-config')->assertOk();
        $access->update(['expires_at' => now()->subMinute()]);
        $this->postJson($url.'/push/subscribe', $this->subscription())->assertNotFound();
        $this->getJson($url.'/manifest.webmanifest')->assertNotFound();
    }

    public function test_browser_subscription_is_encrypted_and_scoped_to_patient_and_lab(): void
    {
        [$lab, $patient, , $url] = $this->fixture();
        [, , , $otherUrl] = $this->fixture();
        $payload = $this->subscription() + ['patient_id' => 9999, 'lab_id' => 9999];
        $this->postJson($url.'/push/subscribe', $payload)->assertOk()->assertJsonPath('subscribed', true);
        $row = PortalPushSubscription::firstOrFail();
        $this->assertEquals($patient->id, $row->patient_id);
        $this->assertEquals($lab->id, $row->lab_id);
        $this->assertStringNotContainsString('fcm.googleapis', DB::table('portal_push_subscriptions')->value('subscription'));
        $this->assertStringNotContainsString('synthetic-browser', $row->toJson());
        $endpoint = ['endpoint' => $payload['subscription']['endpoint']];
        $this->postJson($otherUrl.'/push/status', $endpoint)->assertOk()->assertJsonPath('subscribed', false);
        $this->postJson($otherUrl.'/push/unsubscribe', $endpoint)->assertOk();
        $this->assertDatabaseCount('portal_push_subscriptions', 1);
        // A family member on this browser can opt in independently.
        $this->postJson($otherUrl.'/push/subscribe', $this->subscription())->assertOk();
        $this->postJson($url.'/push/unsubscribe', $endpoint)->assertOk();
        $this->postJson($otherUrl.'/push/status', $endpoint)->assertOk()->assertJsonPath('subscribed', true);
    }

    public function test_untrusted_push_endpoints_and_invalid_keys_are_rejected(): void
    {
        [, , , $url] = $this->fixture();
        foreach (['http://fcm.googleapis.com/push/a', 'https://127.0.0.1/push', 'https://fcm.googleapis.com.evil.test/a',
            'https://user@fcm.googleapis.com/a', 'https://fcm.googleapis.com:443/a', 'https://example.test/a'] as $endpoint) {
            $payload = $this->subscription(); $payload['subscription']['endpoint'] = $endpoint;
            $this->postJson($url.'/push/subscribe', $payload)->assertUnprocessable();
        }
        $payload = $this->subscription(); $payload['subscription']['keys']['p256dh'] = 'invalid';
        $this->postJson($url.'/push/subscribe', $payload)->assertUnprocessable();
        $payload['subscription']['endpoint'] = ['not-a-string'];
        $this->postJson($url.'/push/subscribe', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('portal_push_subscriptions', 0);
    }

    public function test_reading_settings_does_not_consume_opt_in_or_test_limits(): void
    {
        [, , , $url] = $this->fixture();
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->getJson($url.'/app-config')->assertOk();
        }
        $this->postJson($url.'/push/subscribe', $this->subscription())->assertOk();
        $endpoint = ['endpoint' => $this->subscription()['subscription']['endpoint']];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson($url.'/push/test', $endpoint)->assertAccepted();
        }
        $this->postJson($url.'/push/test', $endpoint)->assertTooManyRequests();
        $this->postJson($url.'/push/status', $endpoint)->assertOk();
        [, , , $otherUrl] = $this->fixture();
        $this->postJson($otherUrl.'/push/subscribe', $this->subscription())->assertOk();
        $this->postJson($otherUrl.'/push/test', $endpoint)->assertAccepted();
        $this->assertDatabaseCount('portal_push_deliveries', 4);
    }

    public function test_result_ready_is_queued_once_after_commit_and_never_on_rollback(): void
    {
        [$lab, $patient, , $url] = $this->fixture();
        $this->postJson($url.'/push/subscribe', $this->subscription())->assertOk();
        $invoice = Invoice::create(['patient_id_fk' => $patient->id, 'lab_id_fk' => $lab->id, 'is_done' => false]);
        DB::beginTransaction(); $invoice->update(['is_done' => true]); DB::rollBack();
        $this->assertDatabaseCount('portal_notifications', 0);
        $invoice->refresh()->update(['is_done' => true]);
        $this->assertDatabaseCount('portal_notifications', 1);
        $this->assertDatabaseCount('portal_push_deliveries', 1);
        $invoice->update(['notes' => 'private patient notes']);
        $invoice->update(['is_done' => false]); $invoice->update(['is_done' => true]);
        $this->assertDatabaseCount('portal_notifications', 1);
        $this->assertSame([], $this->sent);
        app(PortalNotifications::class)->deliver();
        $this->assertDatabaseHas('portal_push_deliveries', ['status' => 'accepted']);
        $this->assertCount(1, $this->sent);
        $this->assertStringNotContainsString('private patient notes', json_encode($this->sent));
        $this->assertNull(PortalNotification::first()->read_at);
        app(PortalNotifications::class)->deliver();
        $this->assertCount(1, $this->sent);
    }

    public function test_expiry_otp_and_retracted_results_cancel_pending_delivery(): void
    {
        [$lab, $patient, $access, $url] = $this->fixture();
        $this->postJson($url.'/push/subscribe', $this->subscription())->assertOk();
        $invoice = Invoice::create(['patient_id_fk' => $patient->id, 'lab_id_fk' => $lab->id, 'is_done' => false]);
        $invoice->update(['is_done' => true]); $invoice->update(['is_done' => false]);
        app(PortalNotifications::class)->deliver();
        $this->assertDatabaseHas('portal_push_deliveries', ['status' => 'cancelled']);
        $this->assertSame([], $this->sent);
        $this->postJson($url.'/push/test', ['endpoint' => $this->subscription()['subscription']['endpoint']])->assertAccepted();
        $access->update(['expires_at' => now()->subSecond()]);
        app(PortalNotifications::class)->deliver();
        $this->assertSame([], $this->sent);
        $access->update(['expires_at' => now()->addDay()]);
        $this->postJson($url.'/push/test', ['endpoint' => $this->subscription()['subscription']['endpoint']])->assertAccepted();
        LabSetting::create(['lab_id_fk' => $lab->id, 'loyalty_config' => ['require_otp' => true]]);
        app(PortalNotifications::class)->deliver();
        $this->assertSame([], $this->sent);
    }

    public function test_inbox_read_and_unsubscribe_do_not_cross_patients(): void
    {
        [, , , $url] = $this->fixture(); [, , , $other] = $this->fixture();
        $this->postJson($url.'/push/subscribe', $this->subscription())->assertOk();
        $endpoint = ['endpoint' => $this->subscription()['subscription']['endpoint']];
        $this->postJson($url.'/push/test', $endpoint)->assertAccepted();
        $id = PortalNotification::first()->id;
        $this->getJson($other.'/notifications')->assertOk()->assertJsonPath('unread', 0)->assertJsonCount(0, 'items');
        $this->postJson($other.'/notifications/read', ['ids' => [$id]])->assertOk();
        $this->assertNull(PortalNotification::find($id)->read_at);
        $this->postJson($url.'/notifications/read', ['ids' => [$id]])->assertOk();
        $this->assertNotNull(PortalNotification::find($id)->read_at);
        $this->postJson($url.'/push/unsubscribe', $endpoint)->assertOk();
        app(PortalNotifications::class)->deliver();
        $this->assertSame([], $this->sent);
    }

    public function test_preferences_are_respected_and_offer_campaigns_are_idempotent(): void
    {
        [$lab, $patient, , $url] = $this->fixture();
        $this->postJson($url.'/push/subscribe', $this->subscription(false, false))->assertOk();
        $invoice = Invoice::create(['patient_id_fk' => $patient->id, 'lab_id_fk' => $lab->id, 'is_done' => false]);
        $invoice->update(['is_done' => true]);
        $this->assertDatabaseCount('portal_push_deliveries', 0);
        $args = ['--lab' => $lab->id, '--title' => 'Test offer', '--body' => 'Synthetic offer', '--campaign' => 'test-campaign'];
        $this->artisan('portal:announce', $args)->assertSuccessful();
        $this->assertDatabaseCount('portal_notifications', 1);
        $this->postJson($url.'/push/subscribe', $this->subscription(false, true))->assertOk();
        $this->artisan('portal:announce', $args)->assertSuccessful();
        $this->artisan('portal:announce', $args)->assertSuccessful();
        $this->assertDatabaseCount('portal_notifications', 2);
        $this->assertDatabaseCount('portal_push_deliveries', 1);
        $this->postJson($url.'/push/subscribe', $this->subscription(false, false))->assertOk();
        app(PortalNotifications::class)->deliver();
        $this->assertSame([], $this->sent);
    }

    public function test_temporary_failures_retry_and_expired_endpoints_are_removed(): void
    {
        [, , , $url] = $this->fixture();
        $this->postJson($url.'/push/subscribe', $this->subscription())->assertOk();
        $this->postJson($url.'/push/test', ['endpoint' => $this->subscription()['subscription']['endpoint']])->assertAccepted();
        $this->sendStatus = 'retry'; app(PortalNotifications::class)->deliver();
        $this->assertDatabaseHas('portal_push_deliveries', ['status' => 'queued', 'attempts' => 1]);
        app(PortalNotifications::class)->deliver(); $this->assertCount(1, $this->sent);
        $this->travel(3)->minutes();
        $this->sendStatus = 'expired'; app(PortalNotifications::class)->deliver();
        $this->assertDatabaseCount('portal_push_subscriptions', 0);
        $this->assertDatabaseHas('portal_push_deliveries', ['status' => 'expired', 'attempts' => 2]);
    }
}
