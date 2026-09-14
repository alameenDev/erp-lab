<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class SocialPreviewController extends Controller
{
    /**
     * Social preview for result pages (/result/:invoiceId).
     * Serves OG meta tags to social bots, redirects browsers to SPA.
     */
    public function resultPreview(Request $request, $invoiceId)
    {
        if (! $this->isSocialBot($request)) {
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'https://digitals-labs.com'));
            return $this->frontendResponse($request, $frontendUrl, '/result/' . $invoiceId);
        }

        $invoice = Invoice::with(['patient', 'lab', 'invoiceTestRels'])
            ->find($invoiceId);

        if (! $invoice) {
            return $this->fallbackPreview('Lab Results | Digital Lab', 'نتائج التحاليل المخبرية - Laboratory test results');
        }

        $patientName = $invoice->patient?->name ?? 'Patient';
        $labName = $invoice->lab?->name ?? 'Digital Lab';
        $testCount = $invoice->invoiceTestRels?->count() ?? 0;
        $status = $invoice->is_done ? 'Ready' : 'Pending';
        $statusAr = $invoice->is_done ? 'جاهزة' : 'قيد الانتظار';

        $title = "Lab Results - {$patientName} | {$labName}";
        $description = "نتائج التحاليل لـ {$patientName} - {$testCount} فحص ({$statusAr}). Lab results for {$patientName} - {$testCount} tests ({$status}).";
        $url = rtrim(config('app.url'), '/') . '/result/' . $invoiceId;

        return view('social-preview', compact('title', 'description', 'url', 'labName'));
    }

    /**
     * Social preview for invoice pages (/invoice/:invoiceId).
     * Serves OG meta tags to social bots, redirects browsers to SPA.
     */
    public function invoicePreview(Request $request, $invoiceId)
    {
        if (! $this->isSocialBot($request)) {
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'https://digitals-labs.com'));
            return $this->frontendResponse($request, $frontendUrl, '/invoice/' . $invoiceId);
        }

        $invoice = Invoice::with(['patient', 'lab'])
            ->find($invoiceId);

        if (! $invoice) {
            return $this->fallbackPreview('Invoice | Digital Lab', 'تفاصيل الفاتورة - Invoice details');
        }

        $patientName = $invoice->patient?->name ?? 'Patient';
        $labName = $invoice->lab?->name ?? 'Digital Lab';

        $title = "Invoice - {$patientName} | {$labName}";
        $description = "فاتورة {$patientName} من مختبر {$labName}. Invoice for {$patientName} from {$labName}.";
        $url = rtrim(config('app.url'), '/') . '/invoice/' . $invoiceId;

        return view('social-preview', compact('title', 'description', 'url', 'labName'));
    }

    /**
     * Detect social media bots and crawlers by User-Agent.
     */
    private function frontendResponse(Request $request, string $frontendUrl, string $path)
    {
        if (rtrim($frontendUrl, '/') === $request->getSchemeAndHttpHost()) {
            abort_unless(is_file(public_path('index.html')), 503, 'Frontend build is not published.');
            return response()->file(public_path('index.html'), ['Cache-Control' => 'no-cache']);
        }
        return redirect(rtrim($frontendUrl, '/') . $path);
    }

    private function isSocialBot(Request $request): bool
    {
        $userAgent = $request->header('User-Agent', '');

        $bots = [
            'WhatsApp',
            'facebookexternalhit',
            'Facebot',
            'Twitterbot',
            'TelegramBot',
            'LinkedInBot',
            'Slackbot',
            'Discordbot',
            'Googlebot',
            'bingbot',
            'Baiduspider',
            'YandexBot',
            'DuckDuckBot',
            'Applebot',
            'PinterestBot',
            'Embedly',
            'Quora Link Preview',
            'Showyoubot',
            'outbrain',
            'vkShare',
            'Viber',
            'SkypeUriPreview',
        ];

        foreach ($bots as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return fallback preview for non-existent records.
     */
    private function fallbackPreview(string $title, string $description)
    {
        $url = 'https://digitals-labs.com';
        $labName = 'Digital Lab';

        return view('social-preview', compact('title', 'description', 'url', 'labName'));
    }
}
