<?php

namespace App\Services;

use App\Models\{Invoice, LoyaltyTransaction, Patient, User};

class PatientMobileRead
{
    public function invoices(Patient $patient, User $lab)
    {
        $ids = User::where('id', $lab->id)->orWhere('creator_id', $lab->id)->pluck('id');
        return Invoice::where('patient_id_fk', $patient->id)->whereIn('lab_id_fk', $ids);
    }

    public function patient(Patient $patient, User $user): array
    {
        return ['id'=>$patient->id, 'name'=>$user->name, 'code'=>$patient->code,
            'dob'=>$patient->dob?->toDateString(), 'gender'=>$patient->gender?->gender_type,
            'phone'=>$user->phone_number];
    }

    public function lab(User $lab): array
    {
        return ['id'=>$lab->id, 'name'=>$lab->name, 'phone'=>$lab->phone_number, 'address'=>$lab->address];
    }

    public function reports(Patient $patient, User $lab, int $page): array
    {
        $rows = $this->invoices($patient, $lab)->with(['invoiceTestRels.test', 'invoiceTestRels.culture',
            'invoiceTestRels.package', 'invoiceTestRels.testGroup'])
            ->orderByDesc('id')->simplePaginate(20, ['*'], 'page', $page);
        return ['items'=>$rows->map(fn ($invoice) => $this->summary($invoice))->values()->all(),
            'next_page'=>$rows->hasMorePages() ? $page + 1 : null];
    }

    public function summary(Invoice $invoice): array
    {
        $ready = app(ReportReadiness::class)->isReady($invoice);
        return ['id'=>$invoice->id, 'barcode'=>$invoice->barcode,
            'date'=>$invoice->created_at?->toISOString(), 'result_date'=>$invoice->result_date?->toDateString(),
            'status'=>$ready ? 'ready' : 'pending', 'total'=>(float)$invoice->total,
            'paid'=>(float)$invoice->paid, 'due'=>max(0, (float)$invoice->total - (float)$invoice->paid),
            'tests'=>$invoice->invoiceTestRels->map(fn ($row) => [
                'name'=>$row->test?->name ?? $row->culture?->name ?? $row->package?->name ?? $row->testGroup?->group_name ?? 'فحص',
            ])->values()->all()];
    }

    public function loyalty(Patient $patient, User $lab): array
    {
        $service = app(LoyaltyService::class);
        $config = $service->config($lab); // Read-only: never call refreshSummary().
        $query = LoyaltyTransaction::where('patient_id_fk', $patient->id)->where('lab_id_fk', $lab->id);
        $balance = (int)(clone $query)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->sum('points');
        $yearly = (int)(clone $query)->where('points', '>', 0)->where('created_at', '>=', now()->subYear())->sum('points');
        return ['enabled'=>(bool)($config['enabled'] ?? true), 'balance'=>$balance, 'yearly_points'=>$yearly,
            'tier'=>$service->currentTier($config, $yearly), 'next_tier'=>$service->nextTier($config, $yearly),
            'catalog'=>$config['redemption_catalog']];
    }

    public function ledger(Patient $patient, User $lab, int $page): array
    {
        $rows = LoyaltyTransaction::where('patient_id_fk', $patient->id)->where('lab_id_fk', $lab->id)
            ->orderByDesc('id')->simplePaginate(30, ['id','type','points','description','created_at','expires_at'], 'page', $page);
        return ['items'=>$rows->items(), 'next_page'=>$rows->hasMorePages() ? $page + 1 : null];
    }

    private function arrayValue($value): array
    {
        for ($i = 0; $i < 2 && is_string($value); $i++) $value = json_decode($value, true);
        return is_array($value) ? $value : [];
    }

    private function text($value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function ranges($value): array
    {
        return array_values(array_map(function ($range) {
            if (!is_array($range)) return ['notes'=>$this->text($range)];
            $out = [];
            foreach (['from','to','notes','gender','age_from','age_to','age_unit','test_reference_options','selection_type_options'] as $key) {
                if (isset($range[$key]) && is_scalar($range[$key])) $out[$key] = $this->text($range[$key]);
            }
            return $out;
        }, $this->arrayValue($value)));
    }

    /** Explicit public fields only; stored internal IDs, prices and referral data never leave here. */
    private function measurement(array $item): array
    {
        $parts = [];
        foreach (['sub_tests'=>'value', 'attribute'=>'result'] as $field=>$valueKey) {
            foreach ($this->arrayValue($item[$field] ?? []) as $part) {
                if (!is_array($part)) continue;
                $parts[] = ['name'=>$this->text($part['name'] ?? $part['attribute_name'] ?? ''),
                    'value'=>$this->text($part[$valueKey] ?? ''), 'unit'=>$this->text($part['unit'] ?? $item['unit'] ?? ''),
                    'ranges'=>$this->ranges($part['test_reference_ranges'] ?? $part['sup_test_reference_options'] ?? [])];
            }
        }
        return ['name'=>$this->text($item['name'] ?? 'فحص'), 'value'=>$this->text($item['result'] ?? ''),
            'unit'=>$this->text($item['unit'] ?? ''), 'comment'=>$this->text($item['comment'] ?? ''),
            'ranges'=>$this->ranges($item['test_reference_ranges'] ?? []), 'range_source'=>$item['range_source'] ?? 'stored', 'parts'=>$parts];
    }

    public function report(Patient $patient, User $lab, int $id): array
    {
        $invoice = $this->invoices($patient, $lab)->with(['invoiceTestRels.test.testReferenceRanges.gender',
            'invoiceTestRels.test.testReferenceRanges.ageUnit', 'invoiceTestRels.culture',
            'invoiceTestRels.package', 'invoiceTestRels.testGroup'])->findOrFail($id);
        abort_unless(app(ReportReadiness::class)->isReady($invoice), 409, 'التقرير قيد الإجراء ولم يُعتمد للنشر بعد.');
        $sections = [];
        foreach ($invoice->invoiceTestRels as $row) {
            $name = $row->test?->name ?? $row->culture?->name ?? $row->package?->name ?? $row->testGroup?->group_name ?? 'فحص';
            $items = [];
            if ($row->test_id_fk || $row->culture_id_fk) {
                $ranges = $row->test?->testReferenceRanges
                    ->filter(fn ($r) => !$r->trashed() && (int)$r->lab_id_fk === (int)$invoice->lab_id_fk)
                    ->map(fn ($r) => ['from'=>$r->from,'to'=>$r->to,'notes'=>$r->notes,
                        'gender'=>$r->gender?->gender_type,'age_from'=>$r->age_from,'age_to'=>$r->age_to,
                        'age_unit'=>$r->ageUnit?->unit_name,'test_reference_options'=>$r->selection_type_options])->values()->all() ?? [];
                $items[] = $this->measurement(['name'=>$name, 'result'=>$row->result, 'unit'=>$row->test?->unit,
                    'comment'=>$row->comment, 'sub_tests'=>$row->sub_tests, 'attribute'=>$row->attribute,
                    'test_reference_ranges'=>$ranges, 'range_source'=>'current']);
            } else {
                $container = app(ContainerResultCompletion::class)->resolve($row);
                foreach (['package_tests', 'package_cultures', 'test_group_tests', 'test_group_cultures'] as $column) {
                    // Use the same calculated values as report readiness, without persisting them.
                    $children = $container && $column === $container['prefix'].'_tests'
                        ? $container['tests'] : $this->arrayValue($row->$column);
                    foreach ($children ?? [] as $item) if (is_array($item)) $items[] = $this->measurement($item);
                }
            }
            $sections[] = ['name'=>$this->text($name), 'items'=>$items];
        }
        return ['report'=>$this->summary($invoice), 'sections'=>$sections];
    }
}
