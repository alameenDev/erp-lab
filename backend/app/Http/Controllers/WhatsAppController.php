<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
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

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
            'Content-Type' => 'application/json',
        ])->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages", [
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
