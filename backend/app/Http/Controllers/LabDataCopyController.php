<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LabDataCopier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin endpoints for copying lab reference data between labs.
 * All routes are admin-only (role_id = 1).
 */
class LabDataCopyController extends Controller
{
    private function assertAdmin(): void
    {
        if (Auth::user()?->role_id != 1) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * GET /super-admin/lab-data/{labId}/{type}
     * Returns paginated rows of $type belonging to lab $labId.
     */
    public function list(Request $request, int $labId, string $type)
    {
        $this->assertAdmin();
        $this->assertIsLab($labId);

        $meta = $this->meta($type);
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 50);

        $query = DB::table($meta['table'])
            ->where($meta['labCol'], $labId)
            ->whereNull('deleted_at');

        if ($search) {
            $query->where(function ($q) use ($meta, $search) {
                foreach ($meta['searchCols'] as $col) {
                    $q->orWhere($col, 'ilike', "%{$search}%");
                }
            });
        }

        $data = $query
            ->orderBy('id', 'desc')
            ->select(array_merge(['id'], $meta['displayCols']))
            ->paginate($perPage);

        // Normalise: every row exposes a `label` so the frontend can render
        // any type with one component.
        $items = collect($data->items())->map(function ($row) use ($meta) {
            $label = $row->{$meta['labelCol']} ?? '—';
            $secondary = null;
            if (! empty($meta['secondaryCol'])) {
                $secondary = $row->{$meta['secondaryCol']} ?? null;
            }

            return [
                'id' => $row->id,
                'label' => $label,
                'secondary' => $secondary,
            ];
        });

        return response()->json([
            'data' => $items,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }

    /**
     * POST /super-admin/lab-data/preview
     * Body: { from_lab_id, to_lab_id, selection: { type: [ids] } }
     * Returns dependency closure + dedup summary without writing.
     */
    public function preview(Request $request)
    {
        $this->assertAdmin();
        $payload = $this->validatePayload($request);

        $copier = new LabDataCopier($payload['from_lab_id'], $payload['to_lab_id']);
        $result = $copier->run($payload['selection'], true);

        return response()->json($result);
    }

    /**
     * POST /super-admin/lab-data/copy
     * Body: { from_lab_id, to_lab_id, selection: { type: [ids] } }
     * Performs the copy in a single transaction.
     */
    public function copy(Request $request)
    {
        $this->assertAdmin();
        $payload = $this->validatePayload($request);

        $copier = new LabDataCopier($payload['from_lab_id'], $payload['to_lab_id']);
        try {
            $result = $copier->run($payload['selection'], false);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Copy failed: '.$e->getMessage(),
            ], 500);
        }

        // Activity log entry — admin paper trail
        try {
            (new ActivityLogController)->storeActivity(
                'Copied lab data',
                ['from' => $payload['from_lab_id'], 'to' => $payload['to_lab_id']],
                ['stats' => $result['stats']]
            );
        } catch (\Throwable $e) {
            // logging failure shouldn't roll back the copy
        }

        return response()->json([
            'message' => 'Copy completed',
            'stats' => $result['stats'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function assertIsLab(int $labId): void
    {
        $exists = User::where('id', $labId)->where('role_id', 2)->exists();
        if (! $exists) {
            abort(404, 'Lab not found');
        }
    }

    private function validatePayload(Request $request): array
    {
        $request->validate([
            'from_lab_id' => 'required|integer|different:to_lab_id',
            'to_lab_id' => 'required|integer',
            'selection' => 'required|array',
            'selection.*' => 'array',
            'selection.*.*' => 'integer',
        ]);
        $this->assertIsLab((int) $request->from_lab_id);
        $this->assertIsLab((int) $request->to_lab_id);

        $allowed = ['category', 'sample', 'question', 'antibiotic', 'culture', 'test', 'test_group', 'package'];
        $selection = [];
        foreach ($request->input('selection', []) as $type => $ids) {
            if (! in_array($type, $allowed)) {
                continue;
            }
            $selection[$type] = array_values(array_filter(array_map('intval', (array) $ids)));
        }
        if (empty(array_filter($selection))) {
            abort(422, 'Selection is empty');
        }

        return [
            'from_lab_id' => (int) $request->from_lab_id,
            'to_lab_id' => (int) $request->to_lab_id,
            'selection' => $selection,
        ];
    }

    private function meta(string $type): array
    {
        switch ($type) {
            case 'category':
                return ['table' => 'categories', 'labCol' => 'lab_id_fk', 'displayCols' => ['name'], 'labelCol' => 'name', 'searchCols' => ['name']];
            case 'sample':
                return ['table' => 'samples', 'labCol' => 'lab_id_fk', 'displayCols' => ['sample_name'], 'labelCol' => 'sample_name', 'searchCols' => ['sample_name']];
            case 'question':
                return ['table' => 'patient_questions', 'labCol' => 'lab_id_fk', 'displayCols' => ['question'], 'labelCol' => 'question', 'searchCols' => ['question']];
            case 'antibiotic':
                return ['table' => 'antibiotics', 'labCol' => 'lab_id', 'displayCols' => ['scientific_name', 'common_name', 'short_name'], 'labelCol' => 'scientific_name', 'secondaryCol' => 'short_name', 'searchCols' => ['scientific_name', 'common_name', 'short_name']];
            case 'culture':
                return ['table' => 'cultures', 'labCol' => 'lab_id_fk', 'displayCols' => ['name'], 'labelCol' => 'name', 'searchCols' => ['name']];
            case 'test':
                return ['table' => 'tests', 'labCol' => 'lab_id_fk', 'displayCols' => ['name', 'shortcut', 'report_name'], 'labelCol' => 'name', 'secondaryCol' => 'shortcut', 'searchCols' => ['name', 'shortcut', 'report_name']];
            case 'test_group':
                return ['table' => 'test_groups', 'labCol' => 'lab_id_fk', 'displayCols' => ['group_name', 'shortcut'], 'labelCol' => 'group_name', 'secondaryCol' => 'shortcut', 'searchCols' => ['group_name', 'shortcut']];
            case 'package':
                return ['table' => 'packages', 'labCol' => 'lab_id_fk', 'displayCols' => ['name', 'shortcut'], 'labelCol' => 'name', 'secondaryCol' => 'shortcut', 'searchCols' => ['name', 'shortcut']];
        }
        abort(400, "Unknown type: $type");
    }
}
