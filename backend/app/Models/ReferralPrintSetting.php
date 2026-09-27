<?php
namespace App\Models;
class ReferralPrintSetting extends LabSetting {
 protected $table = 'referral_print_settings';
 protected $hidden = ['ai_config', 'loyalty_config', 'printer_config'];
}
