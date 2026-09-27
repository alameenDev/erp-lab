<?php
namespace App\Http\Controllers;
use App\Models\{Referal, ReferralPrintSetting};
use Illuminate\Http\Request;
class ReferralPrintSettingsController extends LabSettingController {
 protected function settings() { return ReferralPrintSetting::query(); }
 protected function resolveLabOwnerId($user): int {
  abort_unless(Referal::where('referral_id_fk', $user->id)->exists(), 403);
  return (int) $user->id;
 }
 public function show() {
  $id=$this->resolveLabOwnerId(\Illuminate\Support\Facades\Auth::user());
  $response=parent::show();
  $setting=ReferralPrintSetting::where('lab_id_fk',$id)->firstOrFail();
  if(!$setting->lab_display_name) { $setting->update(['lab_display_name'=>\Illuminate\Support\Facades\Auth::user()->name]); return parent::show(); }
  return $response;
 }
 public function update(Request $request) {
  $request->validate(['loyalty_config'=>'prohibited','ai_config'=>'prohibited','printer_config'=>'prohibited']);
  return parent::update($request);
 }
}
