<?php

namespace App\Http\Controllers;

use App\Models\{LabBanner, Referal, User};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Storage};
use Illuminate\Validation\Rule;
use Throwable;

class LabBannerController extends Controller
{
    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && (int) $user->role_id === 1 && !$user->referral_portal_only, 403);
        return $user;
    }

    private function eligibleLabs()
    {
        return User::where('role_id', 2)->where('referral_portal_only', false)->whereDoesntHave('referals');
    }

    private function tenant(Request $request): int
    {
        $request->validate(['lab_id' => 'prohibited']);
        $user = $request->user(); $seen = [];
        for ($depth = 0; $user && $depth < 20; $depth++) {
            $id = (int) $user->id;
            abort_if(isset($seen[$id]) || $user->referral_portal_only
                || in_array((int) $user->role_id, [1, 3, 5], true)
                || Referal::where('referral_id_fk', $id)->exists(), 403);
            $seen[$id] = true;
            if ((int) $user->role_id === 2) return $id;
            $user = $user->creator_id ? User::find($user->creator_id) : null;
        }
        abort(403, 'تعذر تحديد المختبر المرتبط بالحساب.');
    }

    public function labs(Request $request)
    {
        $this->admin($request);
        return response()->json($this->eligibleLabs()->orderBy('name')->get(['id', 'name']));
    }

    public function index(Request $request)
    {
        $this->admin($request);
        $request->validate(['page' => 'nullable|integer|min:1']);
        return response()->json(LabBanner::with('targets:id,name')->orderBy('sort_order')->orderByDesc('id')->paginate(15))
            ->header('Cache-Control', 'private, no-store');
    }

    public function active(Request $request)
    {
        $labId = $this->tenant($request); $now = now()->utc();
        $banners = LabBanner::where('enabled', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->where(fn ($q) => $q->where('audience', 'all')->orWhereHas('targets', fn ($t) => $t->where('users.id', $labId)))
            ->orderBy('sort_order')->orderByDesc('id')->limit(10)->get();
        return response()->json(['data' => $banners->map(fn ($banner) => [
            'id' => $banner->id, 'title' => $banner->title, 'description' => $banner->description,
            'image_url' => $banner->image_url, 'mobile_image_url' => $banner->mobile_image_url,
            'button_label' => $banner->button_label, 'button_url' => $banner->button_url,
            'ends_at' => $banner->ends_at?->toISOString(),
        ]), 'refresh_after' => 60])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request) { return $this->save($request); }
    public function update(Request $request, string $id) { return $this->save($request, $id); }

    private function save(Request $request, ?string $id = null)
    {
        $actor = $this->admin($request);
        $imageRules = ['image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120', 'dimensions:min_width=100,min_height=50,max_width=6000,max_height=6000'];
        $data = $request->validate([
            'request_id' => $id ? 'prohibited' : 'required|uuid',
            'version' => $id ? 'required|integer|min:1' : 'prohibited',
            'title' => 'required|string|max:200', 'description' => 'nullable|string|max:2000',
            'image' => array_merge([$id ? 'nullable' : 'required'], $imageRules),
            'mobile_image' => array_merge(['nullable'], $imageRules),
            'remove_mobile_image' => 'sometimes|boolean',
            'button_label' => 'nullable|required_with:button_url|string|max:80',
            'button_url' => 'nullable|required_with:button_label|url:http,https|max:2048',
            'audience' => ['required', Rule::in(['all', 'selected'])],
            'lab_ids' => 'required_if:audience,selected|prohibited_unless:audience,selected|array|min:1|max:5000',
            'lab_ids.*' => 'required|integer|min:1|distinct',
            'enabled' => 'required|boolean', 'sort_order' => 'required|integer|min:0|max:99999',
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date',
        ]);
        $fields = collect($data)->only(['title', 'description', 'button_label', 'button_url', 'audience', 'enabled', 'sort_order'])->all();
        $fields['title'] = trim($fields['title']);
        abort_if($fields['title'] === '', 422, 'أدخل عنوان البنر.');
        $fields['description'] = $fields['description'] ?? null;
        $fields['button_label'] = $fields['button_label'] ?? null;
        $fields['button_url'] = $fields['button_url'] ?? null;
        if ($fields['button_url']) {
            $url = parse_url($fields['button_url']);
            abort_if(!$url || isset($url['user']) || isset($url['pass']), 422, 'استخدم رابط HTTP أو HTTPS دون بيانات دخول.');
        }
        foreach (['starts_at', 'ends_at'] as $key) {
            $fields[$key] = empty($data[$key]) ? null : Carbon::parse($data[$key])->utc()->toDateTimeString();
        }
        abort_if($fields['starts_at'] && $fields['ends_at'] && $fields['ends_at'] <= $fields['starts_at'], 422, 'تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية.');
        $removeMobile = (bool) ($data['remove_mobile_image'] ?? false);
        abort_if($removeMobile && $request->hasFile('mobile_image'), 422, 'اختر استبدال صورة الموبايل أو حذفها، وليس الاثنين.');
        $ids = array_map('intval', $data['lab_ids'] ?? []); sort($ids, SORT_NUMERIC);
        $fingerprints = [];
        foreach (['image', 'mobile_image'] as $key) {
            $fingerprints[$key] = $request->hasFile($key) ? hash_file('sha256', $request->file($key)->getRealPath()) : null;
        }
        $hash = hash('sha256', json_encode([$fields, $ids, $fingerprints, $removeMobile], JSON_THROW_ON_ERROR));
        $newPaths = []; $oldPaths = [];
        try {
            $banner = DB::transaction(function () use ($request, $actor, $id, $data, $fields, $ids, $hash, $removeMobile, &$newPaths, &$oldPaths) {
                User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                if ($id) {
                    $banner = LabBanner::lockForUpdate()->findOrFail($id);
                    abort_unless($banner->version === (int) $data['version'], 409, 'تم تعديل البنر بواسطة مستخدم آخر. أغلق المحرر وحدّث القائمة قبل إعادة التعديل.');
                } else {
                    $existing = LabBanner::withTrashed()->where('created_by', $actor->id)->where('request_id', $data['request_id'])->first();
                    if ($existing) {
                        abort_unless(!$existing->trashed() && hash_equals($existing->payload_hash, $hash), 409, 'مفتاح الطلب مستخدم لبنر آخر. ابدأ بإضافة بنر جديد.');
                        return $existing;
                    }
                    $banner = new LabBanner(['created_by' => $actor->id, 'request_id' => $data['request_id'], 'version' => 1]);
                }
                if ($fields['audience'] === 'selected') {
                    abort_unless($this->eligibleLabs()->whereIn('id', $ids)->count() === count($ids), 422, 'تأكد من المختبرات المحددة.');
                }
                $banner->fill($fields); $banner->payload_hash = $hash;
                foreach (['image' => 'image_path', 'mobile_image' => 'mobile_image_path'] as $key => $column) {
                    if (!$request->hasFile($key)) continue;
                    $path = $request->file($key)->store('lab-banners', 'public');
                    abort_unless($path, 503, 'تعذر حفظ الصورة. حاول مجدداً.');
                    $newPaths[] = $path;
                    if ($banner->$column) $oldPaths[] = $banner->$column;
                    $banner->$column = $path;
                }
                if ($removeMobile && $banner->mobile_image_path) {
                    $oldPaths[] = $banner->mobile_image_path; $banner->mobile_image_path = null;
                }
                if ($id) $banner->version++;
                $banner->save();
                // Only explicitly selected labs get rows; all means all eligible labs, including future registrations.
                $banner->targets()->sync($fields['audience'] === 'selected' ? $ids : []);
                return $banner;
            });
        } catch (Throwable $error) {
            $this->cleanup($newPaths);
            throw $error;
        }
        $this->cleanup($oldPaths);
        return response()->json(['banner' => $banner->fresh()->load('targets:id,name'), 'message' => 'تم حفظ البنر.'], $id ? 200 : 201);
    }

    private function cleanup(array $paths): void
    {
        if (!$paths) return;
        try { Storage::disk('public')->delete($paths); }
        catch (Throwable $error) { Log::warning('Lab banner file cleanup failed', ['exception' => get_class($error)]); }
    }

    public function destroy(Request $request, string $id)
    {
        $this->admin($request);
        $data = $request->validate(['version' => 'required|integer|min:1']);
        DB::transaction(function () use ($id, $data) {
            $banner = LabBanner::lockForUpdate()->findOrFail($id);
            abort_unless($banner->version === (int) $data['version'], 409, 'تم تعديل البنر. حدّث القائمة قبل الحذف.');
            $banner->enabled = false; $banner->version++; $banner->save(); $banner->delete();
        });
        // Soft deletion retains the assets for recovery; it never removes unrelated uploads.
        return response()->json(['message' => 'تم حذف البنر من العرض.']);
    }
}
