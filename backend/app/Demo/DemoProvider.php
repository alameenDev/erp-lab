<?php
namespace App\Demo;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{Http, Mail, Notification};
class DemoProvider extends ServiceProvider {
    public function boot(): void {
        if (!config('demo.enabled')) return;
        // Also protects queued work/observers and command-line execution in this installation.
        config(['mail.default' => 'log', 'queue.default' => 'sync', 'broadcasting.default' => 'null', 'portal_push.enabled' => false]);
        Http::preventStrayRequests();
        Http::fake(fn () => throw new \RuntimeException('الإرسال الخارجي معطل في الديمو.'));
        Notification::fake();
        Mail::fake();
    }
}
