<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    // get all activity
    public function index()
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('activities view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Activity::with('causer.role');

        // Admin (role_id = 1) can see all activities
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->where(function ($q) use ($users_ids) {
                $q->whereIn('creator_id', $users_ids)->orWhereIn('causer_id', $users_ids);
            });
        }

        $Activity = $query->orderBy('created_at', 'desc')->get();
        if ($Activity->isEmpty()) {
            return response()->json([], 200);
        }

        // Batch-load missing causers to avoid N+1 on old records without causer_type
        $missingIds = $Activity->filter(fn ($a) => ! $a->causer && $a->causer_id)->pluck('causer_id')->unique();
        $missingCausers = $missingIds->isNotEmpty() ? User::with('role')->whereIn('id', $missingIds)->get()->keyBy('id') : collect();

        $Activitys = $Activity->map(function ($activity) use ($missingCausers) {
            $causer = $activity->causer ?: $missingCausers->get($activity->causer_id);
            return [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'subject_id' => $activity->subject_id,
                'subject_type' => last(explode('\\', $activity->subject_type ?? '')),
                'causer_id' => $activity->causer_id,
                'causer_name' => $causer?->name,
                'causer_role' => $causer?->role?->name,
                'properties' => $activity->properties,
                'created_at' => $activity->created_at,
                'updated_at' => $activity->updated_at,
            ];
        });

        return response()->json($Activitys, 200);
    }

    // set activity
    public static function loginActivity($description)
    {
        Activity::create(
            [
                'log_name' => 'تسجيل الدخول',
                'description' => $description,
                'creator_id' => Auth::check() ? Auth::user()->creator_id ?? null : null,
                'causer_id' => Auth::check() ? Auth::user()->id : null,
                'causer_type' => 'App\Models\User',
                'subject_id' => Auth::check() ? Auth::user()->id : null,
                'subject_type' => 'App\Models\User',
            ]
        );
    }

    public static function registerActivity($description)
    {
        Activity::create(
            [
                'log_name' => 'تسجيل حساب',
                'description' => $description,
                'creator_id' => Auth::check() ? Auth::user()->creator_id ?? null : null,
                'causer_id' => Auth::check() ? Auth::user()->id : null,
                'causer_type' => 'App\Models\User',
                'subject_id' => Auth::check() ? Auth::user()->id : null,
                'subject_type' => 'App\Models\User',
            ]
        );
    }

    public static function storeActivity($description, $value)
    {
        Activity::create(
            [
                'log_name' => 'إنشاء',
                'description' => $description,
                'creator_id' => Auth::check() ? Auth::user()->creator_id ?? null : null,
                'causer_id' => Auth::check() ? Auth::user()->id : null,
                'causer_type' => 'App\Models\User',
                'subject_id' => $value?->id,
                'subject_type' => $value?->getMorphClass(),
                'properties' => [
                    'new_value' => $value,
                ],
            ]
        );
    }

    public static function errorActivity($description)
    {
        Activity::create(
            [
                'log_name' => 'خطأ',
                'description' => $description,
                'creator_id' => Auth::check() ? Auth::user()->creator_id ?? null : null,
                'causer_id' => Auth::check() ? Auth::user()->id : null,
                'causer_type' => 'App\Models\User',
                'subject_id' => Auth::check() ? Auth::user()->id : null,
                'subject_type' => 'App\Models\User',
            ]
        );
    }

    public static function updateActivity($description, $old_value, $new_value)
    {
        Activity::create(
            [
                'log_name' => 'تعديل',
                'description' => $description,
                'creator_id' => Auth::check() ? Auth::user()->creator_id ?? null : null,
                'causer_id' => Auth::check() ? Auth::user()->id : null,
                'causer_type' => 'App\Models\User',
                'subject_id' => $new_value?->id,
                'subject_type' => $new_value?->getMorphClass(),
                'properties' => [
                    'old_value' => $old_value,
                    'new_value' => $new_value,
                ],
            ]
        );
    }

    public static function deleteActivity($description, $deleted_value)
    {
        Activity::create(
            [
                'log_name' => 'حذف',
                'description' => $description,
                'creator_id' => Auth::check() ? Auth::user()->creator_id ?? null : null,
                'causer_id' => Auth::check() ? Auth::user()->id : null,
                'causer_type' => 'App\Models\User',
                'subject_id' => $deleted_value?->id,
                'subject_type' => $deleted_value?->getMorphClass(),
                'properties' => [
                    'old_value' => $deleted_value,
                ],
            ]
        );
    }
}
