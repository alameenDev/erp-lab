<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BrowserPushSubscription implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value) || !is_string($value['endpoint'] ?? null) || !is_array($value['keys'] ?? null)) {
            $fail('اشتراك المتصفح غير صالح');
            return;
        }
        // Only known browser push services are eligible for server-side HTTP.
        // No custom hosts, credentials, ports, redirects or private addresses.
        $url = is_array($value) ? parse_url($value['endpoint'] ?? '') : false;
        $host = strtolower($url['host'] ?? '');
        $allowed = $host === 'fcm.googleapis.com' || $host === 'updates.push.services.mozilla.com'
            || $host === 'web.push.apple.com' || preg_match('/^[a-z0-9-]+\.push\.apple\.com$/D', $host)
            || $host === 'notify.windows.com' || preg_match('/^[a-z0-9-]+\.notify\.windows\.com$/D', $host);
        $decode = static function ($text) {
            if (!is_string($text) || !preg_match('/^[A-Za-z0-9_-]+={0,2}$/D', $text)) return false;
            return base64_decode(strtr($text, '-_', '+/'), true);
        };
        $key = $decode($value['keys']['p256dh'] ?? null);
        $auth = $decode($value['keys']['auth'] ?? null);
        if (!$url || ($url['scheme'] ?? '') !== 'https' || !$allowed || isset($url['user'], $url['pass'])
            || isset($url['user']) || isset($url['port']) || isset($url['fragment'])
            || strlen($value['endpoint'] ?? '') > 2048 || empty($url['path'])
            || $key === false || strlen($key) !== 65 || ord($key[0]) !== 4 || $auth === false || strlen($auth) !== 16) {
            $fail('تعذر التحقق من اشتراك هذا المتصفح. أعد تفعيل الإشعارات.');
        }
    }
}
