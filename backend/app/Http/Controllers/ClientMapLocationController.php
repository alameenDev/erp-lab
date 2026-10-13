<?php

namespace App\Http\Controllers;

use App\Models\ClientMapLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Storage};
use Throwable;

class ClientMapLocationController extends Controller
{
    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && (int) $user->role_id === 1 && ! $user->referral_portal_only, 403);

        return $user;
    }

    public function publicIndex()
    {
        $locations = ClientMapLocation::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (ClientMapLocation $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'address' => $location->address,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'logo_url' => $location->logo_url,
            ]);

        return response()->json(['data' => $locations])
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function index(Request $request)
    {
        $this->admin($request);
        $request->validate(['page' => 'nullable|integer|min:1']);

        return response()->json(
            ClientMapLocation::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(30)
        )->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, string $id)
    {
        return $this->save($request, $id);
    }

    private function save(Request $request, ?string $id = null)
    {
        $actor = $this->admin($request);

        $logoRules = [
            'image',
            'mimes:jpg,jpeg,png,webp',
            'mimetypes:image/jpeg,image/png,image/webp',
            'max:5120',
            'dimensions:min_width=40,min_height=40,max_width=4000,max_height=4000',
        ];

        $data = $request->validate([
            'request_id' => $id ? 'prohibited' : 'required|uuid',
            'version' => $id ? 'required|integer|min:1' : 'prohibited',
            'name' => 'required|string|max:180',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'logo' => array_merge([$id ? 'nullable' : 'required'], $logoRules),
            'enabled' => 'required|boolean',
            'sort_order' => 'required|integer|min:0|max:99999',
        ]);

        $fields = [
            'name' => trim($data['name']),
            'address' => trim($data['address']),
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'enabled' => (bool) $data['enabled'],
            'sort_order' => (int) $data['sort_order'],
        ];

        abort_if($fields['name'] === '' || $fields['address'] === '', 422, 'أدخل اسم المختبر وعنوانه.');

        $fingerprint = $request->hasFile('logo')
            ? hash_file('sha256', $request->file('logo')->getRealPath())
            : null;

        $hash = hash('sha256', json_encode([$fields, $fingerprint], JSON_THROW_ON_ERROR));
        $newPaths = [];
        $oldPaths = [];

        try {
            $location = DB::transaction(function () use (
                $request,
                $actor,
                $id,
                $data,
                $fields,
                $hash,
                &$newPaths,
                &$oldPaths
            ) {
                User::whereKey($actor->id)->lockForUpdate()->firstOrFail();

                if ($id) {
                    $location = ClientMapLocation::lockForUpdate()->findOrFail($id);
                    abort_unless(
                        $location->version === (int) $data['version'],
                        409,
                        'تم تعديل بيانات المختبر بواسطة مستخدم آخر. حدّث القائمة ثم حاول مجدداً.'
                    );
                } else {
                    $existing = ClientMapLocation::withTrashed()
                        ->where('created_by', $actor->id)
                        ->where('request_id', $data['request_id'])
                        ->first();

                    if ($existing) {
                        abort_unless(
                            ! $existing->trashed() && hash_equals($existing->payload_hash, $hash),
                            409,
                            'مفتاح الطلب مستخدم لبيانات مختلفة.'
                        );

                        return $existing;
                    }

                    $location = new ClientMapLocation([
                        'created_by' => $actor->id,
                        'request_id' => $data['request_id'],
                        'version' => 1,
                    ]);
                }

                $location->fill($fields);
                $location->payload_hash = $hash;

                if ($request->hasFile('logo')) {
                    $path = $request->file('logo')->store('client-map-logos', 'public');
                    abort_unless($path, 503, 'تعذر حفظ شعار المختبر. حاول مجدداً.');

                    $newPaths[] = $path;
                    if ($location->logo_path) {
                        $oldPaths[] = $location->logo_path;
                    }
                    $location->logo_path = $path;
                }

                if ($id) {
                    $location->version++;
                }

                $location->save();

                return $location;
            });
        } catch (Throwable $error) {
            $this->cleanup($newPaths);
            throw $error;
        }

        $this->cleanup($oldPaths);

        return response()->json([
            'location' => $location->fresh(),
            'message' => 'تم حفظ بيانات المختبر على خريطة العملاء.',
        ], $id ? 200 : 201);
    }

    private function cleanup(array $paths): void
    {
        if (! $paths) {
            return;
        }

        try {
            Storage::disk('public')->delete($paths);
        } catch (Throwable $error) {
            Log::warning('Client map logo cleanup failed', [
                'exception' => get_class($error),
            ]);
        }
    }

    public function destroy(Request $request, string $id)
    {
        $this->admin($request);
        $data = $request->validate(['version' => 'required|integer|min:1']);

        DB::transaction(function () use ($id, $data) {
            $location = ClientMapLocation::lockForUpdate()->findOrFail($id);
            abort_unless(
                $location->version === (int) $data['version'],
                409,
                'تم تعديل بيانات المختبر. حدّث القائمة قبل الحذف.'
            );

            $location->enabled = false;
            $location->version++;
            $location->save();
            $location->delete();
        });

        return response()->json(['message' => 'تم حذف المختبر من خريطة العملاء.']);
    }
}
