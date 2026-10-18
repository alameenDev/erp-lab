<?php

namespace App\Http\Controllers;

use App\Models\WorkRegisterEntry;
use App\Services\{DailyWorkRegister, InventoryService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkRegisterController extends Controller
{
    private function access(Request $request, string $action = 'view'): array
    {
        $user = $request->user();
        $owner = in_array((int) $user->role_id, [1, 2], true);
        abort_if(in_array((int) $user->role_id, [3, 5], true) || $user->referral_portal_only, 403);
        abort_unless($owner || $user->can('patients view'), 403);
        $permissions = ['edit' => $owner || $user->can('patients edit'), 'delete' => $owner || $user->can('patients delete')];
        if ($action !== 'view') abort_unless($permissions[$action], 403);
        return [app(InventoryService::class)->labId($user), $permissions];
    }

    public function index(Request $request, DailyWorkRegister $register)
    {
        [$lab, $permissions] = $this->access($request);
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $date = $data['date'] ?? $register->today();
        $register->synchronize($lab, $date);
        return response()->json($register->report($lab, $date, $permissions))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $id, DailyWorkRegister $register)
    {
        [$lab, $permissions] = $this->access($request, 'edit');
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d', 'version' => 'required|integer|min:0',
            'position' => 'sometimes|required|integer|min:1|max:1000000',
            'patient_name' => 'sometimes|required|string|max:255', 'age' => 'sometimes|nullable|integer|min:0|max:100000',
            'age_unit' => 'sometimes|required|in:Years,Months,Days,Hours',
            'referral_doctor' => 'sometimes|nullable|string|max:255', 'referral_lab' => 'sometimes|nullable|string|max:255',
            'tests' => 'sometimes|array|max:2000', 'tests.*' => 'required|string|max:255',
            'groups' => 'sometimes|array|max:2000', 'groups.*' => 'required|string|max:255',
            'packages' => 'sometimes|array|max:2000', 'packages.*' => 'required|string|max:255',
            'work_register_amount' => 'sometimes|nullable|integer|min:0|max:1000000000000',
            'price' => 'prohibited', 'paid' => 'prohibited',
        ]);
        DB::transaction(function () use ($data, $id, $lab, $register) {
            $day = $register->day($lab, $data['date']);
            $entry = WorkRegisterEntry::where('day_id', $day->id)->where('lab_id_fk', $lab)->findOrFail($id);
            abort_unless((int) $day->version === (int) $data['version'], 409, 'تغيّر السجل. حدّثه قبل حفظ التعديل.');
            $entry->fill(collect($data)->except(['date', 'version', 'position'])->all());
            $entry->save();
            if (isset($data['position'])) $register->move($entry, $data['position']);
            $register->bump($day);
        }, 3);
        return response()->json($register->report($lab, $data['date'], $permissions))->header('Cache-Control', 'private, no-store');
    }

    public function destroy(Request $request, int $id, DailyWorkRegister $register)
    {
        [$lab, $permissions] = $this->access($request, 'delete');
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'version' => 'required|integer|min:0']);
        DB::transaction(function () use ($data, $id, $lab, $register) {
            $day = $register->day($lab, $data['date']);
            $entry = WorkRegisterEntry::where('day_id', $day->id)->where('lab_id_fk', $lab)->findOrFail($id);
            abort_unless((int) $day->version === (int) $data['version'], 409, 'تغيّر السجل. حدّثه قبل الحذف.');
            $entry->delete();
            $register->number($day->id);
            $register->bump($day);
        }, 3);
        return response()->json($register->report($lab, $data['date'], $permissions))->header('Cache-Control', 'private, no-store');
    }
}
