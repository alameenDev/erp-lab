<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Referal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DoctorPortalController extends Controller
{
    private function doctorLabs()
    {
        abort_unless((int) Auth::user()?->role_id === 5 && Auth::user()->referral_portal_only
            && ! \App\Models\ReferralLabProfile::where('user_id_fk', Auth::id())->exists(), 403);
        $ids = Referal::where('referral_id_fk', Auth::id())->pluck('lab_id_fk');
        abort_unless($ids->isNotEmpty(), 403);
        return $ids;
    }

    private function ownInvoices($labIds)
    {
        return Invoice::where('referral_id_fk', Auth::id())->whereIn('lab_id_fk', $labIds);
    }

    public function patients(Request $request)
    {
        $labIds = $this->doctorLabs();
        $validated = $request->validate(['search' => 'nullable|string|max:100']);
        $search = trim((string) ($validated['search'] ?? ''));
        $query = Patient::with('user:id,name')->whereIn('id', $this->ownInvoices($labIds)->select('patient_id_fk'));
        if ($search !== '') $query->where(function ($q) use ($search) {
            $q->where('code', 'like', '%'.$search.'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$search.'%'));
        });
        $patients = $query->orderByDesc('id')->paginate(20);
        $counts = $this->ownInvoices($labIds)->whereIn('patient_id_fk', $patients->getCollection()->pluck('id'))
            ->select('patient_id_fk')->selectRaw('COUNT(*) AS invoice_count')
            ->selectRaw('SUM(CASE WHEN is_done = 1 THEN 1 ELSE 0 END) AS ready_count')
            ->selectRaw('MAX(created_at) AS last_visit')->groupBy('patient_id_fk')->get()->keyBy('patient_id_fk');
        $patients->getCollection()->transform(function ($patient) use ($counts) {
            $row = $counts->get($patient->id);
            return [
                'id' => $patient->id, 'name' => $patient->user?->name,
                'code' => $patient->code, 'invoice_count' => (int) ($row?->invoice_count ?? 0),
                'ready_count' => (int) ($row?->ready_count ?? 0), 'last_visit' => $row?->last_visit,
            ];
        });
        return response()->json($patients);
    }

    private function decode($value): array
    {
        for ($i = 0; $i < 2 && is_string($value); $i++) $value = json_decode($value, true);
        return is_array($value) ? $value : [];
    }

    private function resultRow($name, $result, $unit, $status, $comment, $ranges, bool $ready): array
    {
        return [
            'name' => $name ?: 'فحص', 'result' => $ready ? $result : null,
            'unit' => $unit, 'status' => $ready ? $status : null,
            'comment' => $ready ? $comment : null, 'ranges' => $ranges,
            'ready' => $ready,
        ];
    }

    private function invoiceRows(Invoice $invoice): array
    {
        $groups = [];
        foreach ($invoice->invoiceTestRels as $rel) {
            $test = $rel->test;
            $heading = $test?->report_name ?: ($test?->name ?? $rel->culture?->name ?? $rel->package?->name ?? $rel->testGroup?->group_name ?? 'فحص');
            $ready = (bool) $invoice->is_done && (bool) $rel->is_done;
            $ranges = $test?->testReferenceRanges
                ->filter(fn ($range) => ! $range->trashed() && (int) $range->lab_id_fk === (int) $invoice->lab_id_fk)
                ->map(fn ($range) => [
                    'from' => $range->from, 'to' => $range->to, 'notes' => $range->notes,
                    'gender' => $range->gender?->gender_type,
                    'age_from' => $range->age_from, 'age_to' => $range->age_to,
                    'age_unit' => $range->ageUnit?->unit_name,
                ])->values()->all() ?? [];
            $rows = [];
            if ($test || $rel->culture) {
                $rows[] = $this->resultRow($heading, $rel->result, $test?->unit, $rel->result_status_text, $rel->comment, $ranges, $ready);
            }
            foreach (['sub_tests' => 'value', 'attribute' => 'result'] as $field => $key) {
                foreach ($this->decode($rel->$field) as $item) {
                    if (! is_array($item)) continue;
                    $rows[] = $this->resultRow($item['name'] ?? $item['attribute_name'] ?? null, $item[$key] ?? null,
                        $item['unit'] ?? null, $item['result_status_text'] ?? null, $item['comment'] ?? null, [], $ready);
                }
            }
            foreach (['package_tests', 'package_cultures', 'test_group_tests', 'test_group_cultures'] as $field) {
                foreach ($this->decode($rel->$field) as $item) {
                    if (! is_array($item)) continue;
                    $childReady = $ready && (! array_key_exists('is_done', $item) || filter_var($item['is_done'], FILTER_VALIDATE_BOOLEAN));
                    $rows[] = $this->resultRow($item['name'] ?? null, $item['result'] ?? null, $item['unit'] ?? null,
                        $item['result_status_text'] ?? null, $item['comment'] ?? null, $this->decode($item['test_reference_ranges'] ?? []), $childReady);
                    foreach (['sub_tests' => 'value', 'attribute' => 'result'] as $nested => $key) {
                        foreach ($this->decode($item[$nested] ?? []) as $part) {
                            if (! is_array($part)) continue;
                            $rows[] = $this->resultRow($part['name'] ?? $part['attribute_name'] ?? null, $part[$key] ?? null,
                                $part['unit'] ?? null, $part['result_status_text'] ?? null, $part['comment'] ?? null, [], $childReady);
                        }
                    }
                }
            }
            $groups[] = ['name' => $heading, 'kind' => $test ? 'test' : ($rel->culture ? 'culture' : ($rel->package ? 'package' : 'group')), 'rows' => $rows];
        }
        return $groups;
    }

    public function patientResults($id)
    {
        $labIds = $this->doctorLabs();
        $patient = Patient::with(['user:id,name', 'gender', 'ageUnit'])
            ->whereIn('id', $this->ownInvoices($labIds)->select('patient_id_fk'))->findOrFail($id);
        $invoices = $this->ownInvoices($labIds)->where('patient_id_fk', $patient->id)
            ->with(['lab:id,name', 'invoiceTestRels.test.testReferenceRanges.gender',
                'invoiceTestRels.test.testReferenceRanges.ageUnit', 'invoiceTestRels.culture',
                'invoiceTestRels.package', 'invoiceTestRels.testGroup'])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(10);
        $invoices->getCollection()->transform(fn ($invoice) => [
            'id' => $invoice->id, 'barcode' => $invoice->barcode,
            'lab_name' => $invoice->lab?->name, 'registration_date' => $invoice->registration_date,
            'result_date' => $invoice->result_date, 'created_at' => $invoice->created_at,
            'is_done' => (bool) $invoice->is_done, 'groups' => $this->invoiceRows($invoice),
        ]);
        return response()->json([
            'patient' => ['id' => $patient->id, 'name' => $patient->user?->name, 'code' => $patient->code,
                'age' => $patient->age, 'age_unit' => $patient->ageUnit?->unit_name,
                'gender' => $patient->gender?->gender_type],
            'invoices' => $invoices,
        ]);
    }
}
