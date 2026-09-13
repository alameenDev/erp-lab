<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'name_ar' => 'الأساسية',
                'monthly_price' => 10000,
                'yearly_price' => 100000,
                'currency' => 'IQD',
                'max_users' => 1,
                'max_invoices_per_month' => null,
                'features' => [
                    'إدارة بيانات المرضى',
                    'معالجة الفحوصات',
                    'التقارير الأساسية',
                    'دعم عبر البريد الإلكتروني',
                ],
                'is_popular' => false,
                'is_custom' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Standard',
                'name_ar' => 'القياسية',
                'monthly_price' => 15000,
                'yearly_price' => 150000,
                'currency' => 'IQD',
                'max_users' => 2,
                'max_invoices_per_month' => null,
                'features' => [
                    'جميع مزايا الباقة الأساسية',
                    'الفوترة الذكية',
                    'إرسال عبر واتساب',
                    'دعم ذو أولوية',
                ],
                'is_popular' => false,
                'is_custom' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Professional',
                'name_ar' => 'الاحترافية',
                'monthly_price' => 35000,
                'yearly_price' => 350000,
                'currency' => 'IQD',
                'max_users' => 5,
                'max_invoices_per_month' => null,
                'features' => [
                    'جميع مزايا الباقة القياسية',
                    'لوحة التحليلات',
                    'قوالب مخصصة',
                    'الوصول إلى API',
                ],
                'is_popular' => false,
                'is_custom' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Unlimited',
                'name_ar' => 'غير محدودة',
                'monthly_price' => 75000,
                'yearly_price' => 750000,
                'currency' => 'IQD',
                'max_users' => null,
                'max_invoices_per_month' => null,
                'features' => [
                    'جميع مزايا الباقة الاحترافية',
                    'فروع غير محدودة',
                    'مدير حساب مخصص',
                    'دعم هاتفي 24/7',
                ],
                'is_popular' => true,
                'is_custom' => false,
                'sort_order' => 4,
            ],
            [
                'name' => 'Enterprise',
                'name_ar' => 'المؤسسات',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'currency' => 'IQD',
                'max_users' => null,
                'max_invoices_per_month' => null,
                'features' => [
                    'جميع مزايا الباقة غير المحدودة',
                    'تكاملات مخصصة',
                    'ضمان SLA',
                    'تدريب في الموقع',
                ],
                'is_popular' => false,
                'is_custom' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}
