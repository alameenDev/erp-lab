<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalLabNotificationSetting extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['enabled' => 'boolean', 'results_enabled' => 'boolean', 'announcements_enabled' => 'boolean'];

    public static function defaults(): array
    {
        return ['enabled' => true, 'results_enabled' => true, 'announcements_enabled' => true,
            'result_title' => 'نتائج فحوصاتك جاهزة',
            'result_body' => 'اكتملت جميع فحوصاتك وأصبح تقريرك الطبي جاهزاً. يمكنك الاطلاع على نتائجك واستلامها من خلال بوابة المريض.'];
    }

    public static function forLab(int $lab): array
    {
        return array_replace(self::defaults(), self::where('lab_id_fk', $lab)->first()?->only(array_keys(self::defaults())) ?? []);
    }

    public static function allows(int $lab, string $kind): bool
    {
        $settings = self::forLab($lab);
        return $settings['enabled'] && ($kind === 'result' ? $settings['results_enabled'] : ($kind === 'offer' ? $settings['announcements_enabled'] : true));
    }
}
