<?php

namespace App\Http\Controllers;

use App\Models\LabSetting;
use App\Models\User;
use App\Traits\SecureFileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LabSettingController extends Controller
{
    use SecureFileUpload;

    /**
     * Get current lab's settings (creates default if none exists).
     */
    public function show()
    {
        $user = Auth::user();
        $labOwnerId = $this->resolveLabOwnerId($user);

        $setting = LabSetting::firstOrCreate(
            ['lab_id_fk' => $labOwnerId],
            [
                'primary_color' => '#0d9488',
                'secondary_color' => '#14b8a6',
                'font_family' => 'Tajawal',
                'print_margins' => ['top' => 20, 'bottom' => 20, 'left' => 15, 'right' => 15],
                'show_categories' => true,
                'show_tests_on_barcode' => true,
                'show_test_names' => true,
                'show_status' => true,
                'show_last_result' => false,
                'print_black_white' => false,
            ]
        );

        // Ensure print_margins always has a value
        if (! $setting->print_margins) {
            $setting->print_margins = ['top' => 20, 'bottom' => 20, 'left' => 15, 'right' => 15];
        }

        $setting->loyalty_config = array_replace(\App\Services\LoyaltyService::defaultConfig(), $setting->loyalty_config ?? []);
        return response()->json($setting);
    }

    /**
     * Update branding settings + logo upload.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $labOwnerId = $this->resolveLabOwnerId($user);

        // Only lab owner can edit settings
        if ($user->role_id != 1 && $user->role_id != 2) {
            if ($user->id !== $labOwnerId) {
                return response()->json(['message' => 'Only the lab owner can update settings'], 403);
            }
        }

        $validated = $request->validate([
            'whatsapp_invoice_message' => 'nullable|string|max:3000',
            'whatsapp_result_message' => 'nullable|string|max:3000',
            'document_config' => 'nullable|array:invoice,thermal',
            'document_config.invoice' => 'sometimes|array:paper,orientation,margin,font_size,color,accent,show_barcode,show_qr,footer',
            'document_config.thermal' => 'sometimes|array:width,margin,font_size,show_barcode,show_qr,footer',
            'document_config.invoice.paper' => 'sometimes|in:A4,A5',
            'document_config.invoice.orientation' => 'sometimes|in:portrait,landscape',
            'document_config.thermal.width' => 'sometimes|integer|in:58,80',
            'document_config.invoice.margin' => 'sometimes|numeric|min:0|max:30',
            'document_config.invoice.font_size' => 'sometimes|integer|min:8|max:24',
            'document_config.invoice.show_barcode' => 'sometimes|boolean',
            'document_config.invoice.show_qr' => 'sometimes|boolean',
            'document_config.invoice.footer' => 'sometimes|nullable|string|max:500',
            'document_config.thermal.margin' => 'sometimes|numeric|min:0|max:10',
            'document_config.thermal.font_size' => 'sometimes|integer|min:8|max:24',
            'document_config.thermal.show_barcode' => 'sometimes|boolean',
            'document_config.thermal.show_qr' => 'sometimes|boolean',
            'document_config.thermal.footer' => 'sometimes|nullable|string|max:500',
            'document_config.invoice.color' => 'sometimes|regex:/^#[0-9a-fA-F]{6}$/',
            'document_config.invoice.accent' => 'sometimes|regex:/^#[0-9a-fA-F]{6}$/',
            // Loyalty program (magic-link portal)
            'loyalty_config' => 'nullable|array',
            'loyalty_config.enabled' => 'sometimes|boolean',
            'loyalty_config.require_otp' => 'sometimes|boolean',
            'loyalty_config.points_per_currency' => 'sometimes|numeric|min:0',
            'loyalty_config.welcome_bonus' => 'sometimes|integer|min:0',
            'loyalty_config.checkup_bonus' => 'sometimes|integer|min:0',
            'loyalty_config.review_bonus' => 'sometimes|integer|min:0',
            'loyalty_config.referral_bonus' => 'sometimes|integer|min:0',
            'loyalty_config.points_expiry_months' => 'sometimes|integer|min:1|max:60',
            'loyalty_config.tiers' => 'sometimes|array|min:1|max:10',
            'loyalty_config.tiers.*.key' => 'required_with:loyalty_config.tiers|string|max:40|distinct',
            'loyalty_config.tiers.*.label_ar' => 'sometimes|string',
            'loyalty_config.tiers.*.label_en' => 'sometimes|string',
            'loyalty_config.tiers.*.min_yearly_points' => 'required_with:loyalty_config.tiers|integer|min:0',
            'loyalty_config.tiers.*.multiplier' => 'required_with:loyalty_config.tiers|numeric|min:1',
            'loyalty_config.redemption_catalog' => 'sometimes|array',
            'loyalty_config.redemption_catalog.*.key' => 'required_with:loyalty_config.redemption_catalog|string|max:60|distinct',
            'loyalty_config.redemption_catalog.*.label_ar' => 'sometimes|string',
            'loyalty_config.redemption_catalog.*.discount_amount' => 'sometimes|integer|min:0|max:100000000',
            'loyalty_config.redemption_catalog.*.points' => 'required_with:loyalty_config.redemption_catalog|integer|min:1',
            'primary_color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'secondary_color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'font_family' => 'nullable|string|in:Tajawal,Cairo,Amiri,Inter',
            'lab_display_name' => 'nullable|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            // Print settings
            'print_margins' => 'nullable|array',
            'print_margins.top' => 'nullable|numeric|min:0|max:100',
            'print_margins.bottom' => 'nullable|numeric|min:0|max:100',
            'print_margins.left' => 'nullable|numeric|min:0|max:100',
            'print_margins.right' => 'nullable|numeric|min:0|max:100',
            'show_categories' => 'nullable|boolean',
            'show_tests_on_barcode' => 'nullable|boolean',
            'show_test_names' => 'nullable|boolean',
            'show_status' => 'nullable|boolean',
            'show_last_result' => 'nullable|boolean',
            'print_black_white' => 'nullable|boolean',
            'barcode_config' => 'nullable|array',
            'barcode_config.label_width' => 'nullable|numeric|min:1|max:10',
            'barcode_config.label_height' => 'nullable|numeric|min:0.5|max:10',
            'barcode_config.name_size' => 'nullable|integer|min:4|max:24',
            'barcode_config.info_size' => 'nullable|integer|min:4|max:24',
            'barcode_config.number_size' => 'nullable|integer|min:4|max:24',
            'barcode_config.barcode_height' => 'nullable|integer|min:20|max:100',
            'barcode_config.sample_size' => 'nullable|integer|min:4|max:24',
            'barcode_config.tests_size' => 'nullable|integer|min:4|max:24',
            'patient_header_config' => 'nullable|array',
            'patient_header_config.name_size' => 'nullable|integer|min:8|max:48',
            'patient_header_config.info_size' => 'nullable|integer|min:8|max:32',
            'patient_header_config.barcode_height' => 'nullable|integer|min:15|max:120',
            'patient_header_config.qr_size' => 'nullable|integer|min:30|max:200',
            'patient_header_config.line_height' => 'nullable|numeric|min:1|max:3',
            'print_table_config' => 'nullable|array',
            'print_table_config.header_font_size' => 'nullable|integer|min:6|max:32',
            'print_table_config.header_font_family' => 'nullable|string|max:64',
            'print_table_config.header_color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'print_table_config.header_bg_color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'print_table_config.header_font_weight' => 'nullable|string|in:normal,bold,600,700,800',
            'print_table_config.body_font_size' => 'nullable|integer|min:6|max:32',
            'print_table_config.body_font_family' => 'nullable|string|max:64',
            'print_table_config.body_color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'print_table_config.body_bg_color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'print_table_config.border_color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'print_table_config.cell_padding' => 'nullable|integer|min:0|max:40',
            'print_table_config.section_spacing' => 'nullable|integer|min:0|max:80',
            'report_background' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        $setting = LabSetting::firstOrCreate(
            ['lab_id_fk' => $labOwnerId],
            ['primary_color' => '#0d9488', 'secondary_color' => '#14b8a6', 'font_family' => 'Tajawal']
        );

        // Handle logo upload
        if ($request->hasFile('logo')) {
            if ($setting->getRawOriginal('logo')) {
                $this->safeDeleteFile($setting->getRawOriginal('logo'));
            }
            $result = $this->secureUploadImage($request->file('logo'), 'logos');
            if (! $result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $setting->logo = $result['path']; // store relative path only
        }

        // Handle report background upload
        if ($request->hasFile('report_background')) {
            if ($setting->getRawOriginal('report_background')) {
                $this->safeDeleteFile($setting->getRawOriginal('report_background'));
            }
            $result = $this->secureUploadImage($request->file('report_background'), 'backgrounds');
            if (! $result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $setting->report_background = $result['path']; // store relative path only
        }

        // Update branding fields
        $brandingFields = ['primary_color', 'secondary_color', 'font_family'];
        foreach ($brandingFields as $field) {
            if (isset($validated[$field])) {
                $setting->$field = $validated[$field];
            }
        }

        // Update nullable string fields
        foreach (['lab_display_name', 'tagline'] as $field) {
            if (array_key_exists($field, $validated)) {
                $setting->$field = $validated[$field];
            }
        }

        // Update print settings
        if (isset($validated['print_margins'])) {
            $setting->print_margins = $validated['print_margins'];
        }
        foreach (['show_categories', 'show_tests_on_barcode', 'show_test_names', 'show_status', 'show_last_result', 'print_black_white'] as $field) {
            if (array_key_exists($field, $request->all())) {
                $setting->$field = filter_var($request->$field, FILTER_VALIDATE_BOOLEAN);
            }
        }

        // Update barcode config
        if (isset($validated['barcode_config'])) {
            $setting->barcode_config = $validated['barcode_config'];
        }

        // Update patient-header config (font sizes for the print/PDF/WhatsApp result header)
        if (isset($validated['patient_header_config'])) {
            $setting->patient_header_config = $validated['patient_header_config'];
        }

        // Update print-table config (font/colors/padding for the result tables)
        if (isset($validated['print_table_config'])) {
            $setting->print_table_config = $validated['print_table_config'];
        }

        foreach (['whatsapp_invoice_message', 'whatsapp_result_message'] as $field) {
            if (array_key_exists($field, $validated)) $setting->$field = $validated[$field];
        }
        if (isset($validated['document_config'])) {
            $setting->document_config = array_replace_recursive($setting->document_config ?? [], $validated['document_config']);
        }
        if (isset($validated['loyalty_config'])) {
            // tiers/redemption_catalog are whole-list replacements when sent (not merged item by item)
            $setting->loyalty_config = array_replace($setting->loyalty_config ?? [], $validated['loyalty_config']);
        }
        $setting->save();

        Log::info('Lab settings updated', ['lab_id' => $labOwnerId, 'user_id' => $user->id]);

        return response()->json([
            'message' => 'Settings updated successfully',
            'setting' => $setting,
        ]);
    }

    /**
     * Public endpoint — for print templates and public result pages.
     */
    public function showPublic($labId)
    {
        $setting = LabSetting::where('lab_id_fk', $labId)->first();

        if (! $setting) {
            return response()->json([
                'primary_color' => '#0d9488',
                'secondary_color' => '#14b8a6',
                'font_family' => 'Tajawal',
                'logo' => null,
                'lab_display_name' => null,
                'tagline' => null,
                'print_margins' => ['top' => 20, 'bottom' => 20, 'left' => 15, 'right' => 15],
                'show_categories' => true,
                'show_tests_on_barcode' => true,
                'show_test_names' => true,
                'show_status' => true,
                'show_last_result' => false,
                'print_black_white' => false,
                'barcode_config' => ['label_width' => 3, 'label_height' => 1.5, 'name_size' => 9, 'info_size' => 7, 'number_size' => 6, 'barcode_height' => 40, 'sample_size' => 8, 'tests_size' => 7],
                'report_background' => null,
            ]);
        }

        return response()->json($setting);
    }

    /**
     * Remove logo.
     */
    public function removeLogo()
    {
        $user = Auth::user();
        $labOwnerId = $this->resolveLabOwnerId($user);

        if (! in_array((int) $user->role_id, [1, 2], true) && (int) $user->id !== $labOwnerId) {
            return response()->json(['message' => 'Only the lab owner can update settings'], 403);
        }

        $setting = LabSetting::where('lab_id_fk', $labOwnerId)->first();
        if (! $setting || ! $setting->logo) {
            return response()->json(['message' => 'No logo to remove'], 404);
        }

        $this->safeDeleteFile($setting->getRawOriginal('logo'));
        $setting->update(['logo' => null]);

        return response()->json(['message' => 'Logo removed']);
    }

    /**
     * Delete the report background image from disk + DB.
     */
    public function removeBackground()
    {
        $user = Auth::user();
        $labOwnerId = $this->resolveLabOwnerId($user);

        if (! in_array((int) $user->role_id, [1, 2], true) && (int) $user->id !== $labOwnerId) {
            return response()->json(['message' => 'Only the lab owner can update settings'], 403);
        }

        $setting = LabSetting::where('lab_id_fk', $labOwnerId)->first();
        if (! $setting || ! $setting->getRawOriginal('report_background')) {
            return response()->json(['message' => 'No background to remove'], 404);
        }

        $this->safeDeleteFile($setting->getRawOriginal('report_background'));
        $setting->update(['report_background' => null]);

        return response()->json(['message' => 'Background removed', 'setting' => $setting->fresh()]);
    }

    /**
     * Reset all branding to defaults.
     */
    public function reset(Request $request)
    {
        $user = Auth::user();
        $labOwnerId = $this->resolveLabOwnerId($user);
        $request->validate(['section' => 'required|in:branding,print,all']);
        $section = $request->input('section', 'all'); // branding, print, or all

        if ($user->role_id != 1 && $user->role_id != 2 && $user->id !== $labOwnerId) {
            return response()->json(['message' => 'Only the lab owner can reset settings'], 403);
        }

        $setting = LabSetting::where('lab_id_fk', $labOwnerId)->first();
        if (! $setting) {
            return response()->json(['message' => 'No settings to reset'], 404);
        }

        if ($section === 'branding') {
            if ($setting->getRawOriginal('logo')) {
                $this->safeDeleteFile($setting->getRawOriginal('logo'));
            }
            $setting->update([
                'logo' => null,
                'primary_color' => '#0d9488',
                'secondary_color' => '#14b8a6',
                'font_family' => 'Tajawal',
                'lab_display_name' => null,
                'tagline' => null,
            ]);
        } elseif ($section === 'print') {
            if ($setting->getRawOriginal('report_background')) {
                $this->safeDeleteFile($setting->getRawOriginal('report_background'));
            }
            $setting->update([
                'print_margins' => null,
                'show_categories' => true,
                'show_tests_on_barcode' => true,
                'show_test_names' => true,
                'show_status' => true,
                'show_last_result' => false,
                'print_black_white' => false,
                'barcode_config' => null,
                'patient_header_config' => null,
                'print_table_config' => null,
                'document_config' => null,
                'report_background' => null,
            ]);
        } else {
            // Delete entire record
            if ($setting->getRawOriginal('logo')) {
                $this->safeDeleteFile($setting->getRawOriginal('logo'));
            }
            if ($setting->getRawOriginal('report_background')) {
                $this->safeDeleteFile($setting->getRawOriginal('report_background'));
            }
            $setting->delete();

            return response()->json([
                'message' => 'Settings deleted',
                'setting' => null,
            ]);
        }

        $setting->refresh();

        Log::info('Lab settings reset to defaults', ['lab_id' => $labOwnerId, 'user_id' => $user->id]);

        return response()->json([
            'message' => 'Settings reset to defaults',
            'setting' => $setting,
        ]);
    }

    private function resolveLabOwnerId($user): int
    {
        if ($user->role_id == 2) {
            return $user->id;
        }

        $labOwnerId = $user->creator_id ?? $user->id;
        if ($labOwnerId !== $user->id) {
            $creator = User::find($labOwnerId);
            if ($creator && $creator->role_id != 2 && $creator->creator_id) {
                $labOwnerId = $creator->creator_id;
            }
        }

        return $labOwnerId;
    }
}
