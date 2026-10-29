<?php
// Called only by the licensed launcher, also after restoring an older backup.
$app = require __DIR__.'/bootstrap.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$type = getenv('DL_LICENSE_TYPE');
if (!in_array($type, ['annual', 'lifetime'], true)) throw new \RuntimeException('Missing verified desktop license.');
$end = $type === 'lifetime' ? '9999-12-31' : \Carbon\Carbon::parse(getenv('DL_LICENSE_EXPIRY'))->timezone('Asia/Baghdad')->toDateString();
// Preserve deliberate local suspension. The outer license gate is authoritative.
foreach (\App\Models\User::where('role_id', 2)->pluck('id') as $labId) {
    $subscription = \App\Models\Subscription::where('lab_id_fk', $labId)->latest('id')->first();
    if ($subscription && $subscription->status !== 'suspended') {
        $subscription->update(['status' => 'active', 'end_date' => $end,
            'notes' => $type === 'lifetime' ? 'ترخيص سطح المكتب مدى الحياة' : 'ترخيص سطح المكتب سنوي؛ التجديد بملف تفعيل موقّع']);
    }
}
echo json_encode(['synced' => true]);
