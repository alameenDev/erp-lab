<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabSetting extends Model
{
    protected $table = 'lab_settings';

    protected $fillable = [
        'lab_id_fk',
        'document_config',
        'loyalty_config',
        'printer_config',
        'ai_config',
        'whatsapp_invoice_message',
        'whatsapp_result_message',
        'logo',
        'primary_color',
        'secondary_color',
        'font_family',
        'lab_display_name',
        'tagline',
        'print_margins',
        'show_categories',
        'show_tests_on_barcode',
        'show_test_names',
        'show_status',
        'show_last_result',
        'print_black_white',
        'barcode_config',
        'patient_header_config',
        'print_table_config',
        'report_background',
    ];

    protected function casts(): array
    {
        return [
            'print_margins' => 'json',
            'document_config' => 'array',
            'loyalty_config' => 'array',
            'printer_config' => 'array',
            'ai_config' => 'encrypted:array',
            'show_categories' => 'boolean',
            'show_tests_on_barcode' => 'boolean',
            'show_test_names' => 'boolean',
            'show_status' => 'boolean',
            'show_last_result' => 'boolean',
            'print_black_white' => 'boolean',
            'barcode_config' => 'json',
            'patient_header_config' => 'json',
            'print_table_config' => 'json',
        ];
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk')->withTrashed();
    }

    /**
     * Convert relative path to full URL at read time.
     * If value already starts with http(s), return as-is (backwards compat).
     */
    protected function buildUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return rtrim(config('app.url'), '/').'/storage/'.ltrim($value, '/');
    }

    public function getLogoAttribute(?string $value): ?string
    {
        return $this->buildUrl($value);
    }

    public function getReportBackgroundAttribute(?string $value): ?string
    {
        return $this->buildUrl($value);
    }
}
