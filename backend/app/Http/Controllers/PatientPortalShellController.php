<?php

namespace App\Http\Controllers;

use DOMDocument;
use DOMElement;

class PatientPortalShellController extends Controller
{
    public function __invoke(string $token)
    {
        // Safari can read installation metadata before the SPA mounts. Never
        // serve the staff app's root manifest as the initial portal document.
        // This is only the public shell; patient data and the manifest still
        // pass through the existing token, expiry and OTP checks in the API.
        $entry = public_path('index.html');
        abort_unless(is_file($entry), 503, 'واجهة البوابة غير متاحة حالياً. يرجى المحاولة لاحقاً.');
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.file_get_contents($entry));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        foreach (iterator_to_array($document->childNodes) as $node) {
            if ($node->nodeType === XML_PI_NODE) { $document->removeChild($node); }
        }
        $head = $document->getElementsByTagName('head')->item(0);
        abort_unless($head, 503);
        foreach (iterator_to_array($head->childNodes) as $node) {
            if (!$node instanceof DOMElement) { continue; }
            $remove = $node->tagName === 'title'
                || ($node->tagName === 'meta' && !$node->hasAttribute('charset') && $node->getAttribute('name') !== 'viewport')
                || ($node->tagName === 'link' && in_array($node->getAttribute('rel'), ['manifest', 'canonical', 'alternate'], true))
                || ($node->tagName === 'script' && $node->getAttribute('type') === 'application/ld+json');
            if ($remove) { $head->removeChild($node); }
        }
        $append = function (string $tag, array $attributes) use ($document, $head): DOMElement {
            $element = $document->createElement($tag);
            foreach ($attributes as $key => $value) { $element->setAttribute($key, $value); }
            $head->appendChild($element);
            return $element;
        };
        $append('title', [])->appendChild($document->createTextNode('بوابة المريض'));
        $append('meta', ['name' => 'description', 'content' => 'بوابة المريض لمتابعة نتائج المختبر']);
        $append('meta', ['name' => 'robots', 'content' => 'noindex, nofollow']);
        $append('meta', ['name' => 'theme-color', 'content' => '#0f766e']);
        foreach (['apple-mobile-web-app-capable' => 'yes', 'mobile-web-app-capable' => 'yes', 'apple-mobile-web-app-title' => 'بوابة المريض'] as $name => $content) {
            $append('meta', ['name' => $name, 'content' => $content, 'data-portal-app-head' => 'true']);
        }
        $append('link', ['rel' => 'canonical', 'href' => '/portal/'.$token]);
        $append('link', ['rel' => 'manifest', 'href' => '/api/portal/'.$token.'/manifest.webmanifest', 'data-portal-app-head' => 'true']);

        return response($document->saveHTML())->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
