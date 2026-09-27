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
  $user=\Illuminate\Support\Facades\Auth::user();
  $id=$this->resolveLabOwnerId($user);
  $response=parent::show();
  $setting=ReferralPrintSetting::where('lab_id_fk',$id)->firstOrFail();
  $profile=\App\Models\ReferralLabProfile::where('user_id_fk',$id)->first();
  $changed=false;
  if(!$setting->lab_display_name) { $setting->lab_display_name=$profile?->display_name ?: $user->name; $changed=true; }
  $oldLogo=$profile?->getRawOriginal('logo');
  if(!$setting->logo && $oldLogo && \Illuminate\Support\Facades\Storage::disk('public')->exists($oldLogo)) {
   $newLogo='referral-logos/print-'.\Illuminate\Support\Str::uuid().'.'.pathinfo($oldLogo,PATHINFO_EXTENSION);
   if(\Illuminate\Support\Facades\Storage::disk('public')->copy($oldLogo,$newLogo)) { $setting->logo=$newLogo; $changed=true; }
  }
  if($changed) { $setting->save(); return parent::show(); }
  return $response;
 }
 public function update(Request $request) {
  $request->validate(['loyalty_config'=>'prohibited','ai_config'=>'prohibited','printer_config'=>'prohibited']);
  return parent::update($request);
 }
}
