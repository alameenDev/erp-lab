<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\LabSetting;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function sendMedicalReport(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'report' => 'required|file|mimes:pdf|max:20480',
        ]);

        $actor = Auth::user();
        abort_unless($actor && $actor->hasPermissionTo('invoices send whatsapp'), 403);
        $invoice = Invoice::with(['patient.user', 'lab'])->findOrFail($data['invoice_id']);
        abort_unless(
            (int) $actor->role_id === 1 || ($invoice->lab && app(InventoryService::class)->labId($actor) === app(InventoryService::class)->labId($invoice->lab)),
            403
        );

        $patient = $invoice->patient;
        $digits = preg_replace('/\D+/', '', (string) ($patient?->user?->phone ?? ''));
        if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
        if (str_starts_with($digits, '0')) $digits = '964'.substr($digits, 1);
        if (! str_starts_with($digits, '964')) $digits = '964'.$digits;
        if (strlen($digits) < 12 || strlen($digits) > 15) {
            return response()->json(['message' => 'رقم هاتف المريض المسجل غير صالح'], 422);
        }

        $accessToken = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        if (! $accessToken || $accessToken === 'YOUR_ACCESS_TOKEN' || ! $phoneNumberId) {
            return response()->json(['message' => 'إعدادات WhatsApp Cloud API غير مكتملة'], 503);
        }

        // The existing portal endpoint performs the same patient/lab authorization
        // and reuses a valid token instead of creating a new link for each send.
        $portalResponse = app(PatientPortalController::class)->generateLink(
            Request::create('/api/portal/generate', 'POST', ['patient_id' => $patient->id])
        );
        if ($portalResponse->getStatusCode() !== 200) return $portalResponse;
        $portalData = $portalResponse->getData(true);
        $portalUrl = $portalData['url'];
        $ownerId = $invoice->lab ? app(InventoryService::class)->labId($invoice->lab) : $invoice->lab_id_fk;
        $settings = LabSetting::where('lab_id_fk', $ownerId)->first();
        $labName = $settings?->lab_display_name ?: $invoice->lab?->name ?: 'المختبر';
        $patientName = $patient->user?->name ?: 'المريض';
        $default = "عزيزي/عزيزتي {$patientName}،\nيسر {$labName} إبلاغك بأن تقريرك الطبي جاهز ومرفق بهذه الرسالة.\nيمكنك أيضاً متابعة نتائجك وسجل فحوصاتك من بوابة المريض:\n{$portalUrl}\nنتمنى لك دوام الصحة والعافية.";
        $caption = trim(strtr($settings?->whatsapp_result_message ?: $default, [
            '{patient_name}' => $patientName,
            '{lab_name}' => $labName,
            '{invoice_number}' => (string) $invoice->id,
            '{link}' => $portalUrl,
            '{loyalty_points}' => (string) ($portalData['loyalty_points'] ?? ''),
            '{loyalty_tier}' => (string) ($portalData['loyalty_tier'] ?? ''),
        ]));
        if (! str_contains($caption, $portalUrl)) $caption .= "\n".$portalUrl;
        if (! config('services.whatsapp.result_template_name') && mb_strlen($caption) > 1024) {
            return response()->json(['message' => 'رسالة التقرير طويلة جداً لإرفاقها مع PDF (الحد 1024 حرف)'], 422);
        }

        $endpoint = 'https://graph.facebook.com/'.config('services.whatsapp.graph_version')."/{$phoneNumberId}";
        $upload = Http::withToken($accessToken)
            ->attach('file', file_get_contents($data['report']->getRealPath()), 'medical-report-'.$invoice->id.'.pdf')
            ->post($endpoint.'/media', ['messaging_product' => 'whatsapp', 'type' => 'application/pdf']);
        $mediaId = $upload->json('id');
        if ($upload->failed() || ! $mediaId) {
            Log::error('WhatsApp report media upload failed', ['invoice_id' => $invoice->id, 'status' => $upload->status()]);
            return response()->json(['message' => 'تعذر رفع ملف التقرير إلى واتساب'], 502);
        }

        // An approved utility template enables business-initiated delivery
        // outside Meta's customer service window. Its header must be a PDF
        // document and its body parameters: patient, lab, portal URL.
        $templateName = config('services.whatsapp.result_template_name');
        $content = $templateName ? [
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => config('services.whatsapp.result_template_language', 'ar')],
                'components' => [
                    ['type' => 'header', 'parameters' => [
                        ['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => 'medical-report-'.$invoice->id.'.pdf']],
                    ]],
                    ['type' => 'body', 'parameters' => [
                        ['type' => 'text', 'text' => $patientName],
                        ['type' => 'text', 'text' => $labName],
                        ['type' => 'text', 'text' => $portalUrl],
                    ]],
                ],
            ],
        ] : [
            'type' => 'document',
            'document' => [
                'id' => $mediaId,
                'filename' => 'medical-report-'.$invoice->id.'.pdf',
                'caption' => $caption,
            ],
        ];
        $sent = Http::withToken($accessToken)->post($endpoint.'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $digits,
            ...$content,
        ]);
        if ($sent->failed()) {
            Log::error('WhatsApp report send failed', ['invoice_id' => $invoice->id, 'status' => $sent->status(), 'code' => $sent->json('error.code')]);
            return response()->json(['message' => 'تعذر إرسال التقرير. تحقق من نافذة المحادثة أو قالب واتساب المعتمد.'], 502);
        }

        $invoice->update(['sent_to_patient' => true]);
        return response()->json(['message' => 'تم إرسال التقرير ورابط البوابة', 'phone' => $digits]);
    }

    public static function sendWhatsAppMessage(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'message' => 'required|string|max:4096',
        ]);

        $accessToken = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if (! $accessToken || $accessToken === 'YOUR_ACCESS_TOKEN' || ! $phoneNumberId) {
            Log::warning('WhatsApp API not configured — message not sent', ['phone' => $request->phone]);

            return response()->json(['message' => 'WhatsApp API not configured'], 503);
        }

        $endpoint = 'https://graph.facebook.com/'.config('services.whatsapp.graph_version')."/{$phoneNumberId}/messages";
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
            'Content-Type' => 'application/json',
        ])->post($endpoint, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $request->phone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $request->message,
            ],
        ]);

        if ($response->failed()) {
            Log::error('WhatsApp send failed', ['status' => $response->status(), 'body' => $response->body()]);

            return response()->json(['message' => 'Failed to send message'], $response->status());
        }

        return response()->json(['message' => 'Sent successfully'], 200);
    }
}
