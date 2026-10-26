<?php

namespace App\Services;

use App\Models\{AgeUnit, Patient};
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Calendar-based age entry; an age-derived date is explicitly approximate. */
class PatientAge
{
    public function normalize(array $data, ?Patient $patient = null, string $prefix = ''): array
    {
        // Preserve legacy age-only requests and unrelated partial updates.
        if (! array_key_exists('dob_is_estimated', $data) && empty($data['dob'])) return $data;
        $today = CarbonImmutable::today('Asia/Baghdad');
        $estimated = (bool) ($data['dob_is_estimated'] ?? $patient?->dob_is_estimated ?? false);
        $dob = $data['dob'] ?? null;
        if ($dob && ! $estimated) {
            $birth = CarbonImmutable::parse(CarbonImmutable::parse($dob)->toDateString(), 'Asia/Baghdad')->startOfDay();
            if ($birth->year < 1 || $birth->greaterThan($today)) $this->invalid($prefix.'dob');
            $months = ($today->year - $birth->year) * 12 + $today->month - $birth->month;
            if ($birth->addMonthsNoOverflow($months)->greaterThan($today)) $months--;
            [$age, $unit] = $months >= 12 ? [intdiv($months, 12), 'Years']
                : ($months >= 1 ? [$months, 'Months'] : [(int) $birth->diffInDays($today), 'Days']);
            $unitId = AgeUnit::where('unit_name', $unit)->value('id');
            if (! $unitId) $this->invalid($prefix.'age_unit_id_fk');
            return array_replace($data, ['dob' => $birth->toDateString(), 'dob_is_estimated' => false, 'age' => $age, 'age_unit_id_fk' => $unitId]);
        }
        $age = $data['age'] ?? $patient?->age;
        $unitId = $data['age_unit_id_fk'] ?? $patient?->age_unit_id_fk;
        if ($age === null || $age === '' || filter_var($age, FILTER_VALIDATE_INT) === false || (int) $age < 0) $this->invalid($prefix.'age');
        // Do not move a saved estimated birthday on an unrelated edit.
        if ($estimated && $patient?->dob_is_estimated && $patient->dob
            && (int) $age === $patient->age && (int) $unitId === (int) $patient->age_unit_id_fk
            && $dob && CarbonImmutable::parse($dob)->toDateString() === $patient->dob->toDateString()) {
            return array_replace($data, ['dob' => $patient->dob->toDateString(), 'dob_is_estimated' => true]);
        }
        $unit = AgeUnit::find($unitId)?->unit_name;
        $age = (int) $age;
        // Bounds avoid calendar overflow; retain the existing range of age units.
        if ($age > 50000) $this->invalid($prefix.'age');
        $birth = match ($unit) {
            'Years' => $today->subYearsNoOverflow($age),
            'Months' => $today->subMonthsNoOverflow($age),
            'Days' => $today->subDays($age),
            default => null,
        };
        if (! $birth || $birth->year < 1) $this->invalid($prefix.'age');
        return array_replace($data, ['dob' => $birth->toDateString(), 'dob_is_estimated' => true, 'age' => $age, 'age_unit_id_fk' => $unitId]);
    }

    private function invalid(string $field): never
    {
        throw ValidationException::withMessages([$field => 'أدخل عمراً أو تاريخ ميلاد صحيحاً.']);
    }
}
