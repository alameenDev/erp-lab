<?php

namespace App\Services;

/**
 * Prevent different catalog tests or ambiguous patient reference ranges from
 * being silently merged when an Excel file repeats an analysis name.
 */
final class TestImportDuplicateGuard
{
    public const CATALOG_FIELDS = [
        'shortcut', 'report_name', 'category', 'sample', 'result_type', 'unit',
        'price', 'for_customer_price', 'order', 'test_duration', 'duration_unit',
        'interface_code',
    ];

    public static function normalize(mixed $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($value ?? ''))));
    }

    /** Blank cells on continuation rows inherit the first row's catalog data. */
    public static function catalogDifferences(array $original, array $row): array
    {
        $differences = [];
        foreach (self::CATALOG_FIELDS as $field) {
            $new = trim((string) ($row[$field] ?? ''));
            if ($new === '') {
                continue;
            }
            $old = trim((string) ($original[$field] ?? ''));
            if (is_numeric($new) && is_numeric($old)
                ? (float) $new !== (float) $old
                : self::normalize($new) !== self::normalize($old)) {
                $differences[] = $field;
            }
        }

        return $differences;
    }

    /**
     * add: distinct patient population; duplicate: identical rule already stored;
     * conflict: overlapping patient population (even if values differ only in notes).
     */
    public static function rangeDecision(array $incoming, array $known, array $genderNames, array $ageUnitNames): string
    {
        foreach ($known as $prior) {
            if (! self::genderOverlaps($incoming, $prior, $genderNames)
                || ! self::ageOverlaps($incoming, $prior, $ageUnitNames)) {
                continue;
            }

            $samePopulation = self::nullableId($incoming['gender_id_fk'] ?? null) === self::nullableId($prior['gender_id_fk'] ?? null)
                && self::nullableId($incoming['age_unit_id_fk'] ?? null) === self::nullableId($prior['age_unit_id_fk'] ?? null)
                && self::nullableNumber($incoming['age_from'] ?? null) === self::nullableNumber($prior['age_from'] ?? null)
                && self::nullableNumber($incoming['age_to'] ?? null) === self::nullableNumber($prior['age_to'] ?? null);
            $sameValues = self::nullableNumber($incoming['from'] ?? null) === self::nullableNumber($prior['from'] ?? null)
                && self::nullableNumber($incoming['to'] ?? null) === self::nullableNumber($prior['to'] ?? null)
                && self::normalize($incoming['notes'] ?? '') === self::normalize($prior['notes'] ?? '');

            if ($samePopulation && $sameValues) {
                return 'duplicate';
            }

            // Two matching rules for the same patient would make report normals ambiguous.
            return 'conflict';
        }

        return 'add';
    }

    private static function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function nullableNumber(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private static function genderOverlaps(array $a, array $b, array $names): bool
    {
        $aId = self::nullableId($a['gender_id_fk'] ?? null);
        $bId = self::nullableId($b['gender_id_fk'] ?? null);
        $aGender = self::normalize($names[$aId] ?? 'both');
        $bGender = self::normalize($names[$bId] ?? 'both');
        $all = ['', 'both', 'all', 'كلاهما', 'الجميع'];
        return in_array($aGender, $all, true) || in_array($bGender, $all, true) || $aId === $bId;
    }

    private static function ageOverlaps(array $a, array $b, array $names): bool
    {
        [$fromA, $toA] = self::ageBounds($a, $names);
        [$fromB, $toB] = self::ageBounds($b, $names);
        return $fromA <= $toB && $fromB <= $toA;
    }

    private static function ageBounds(array $range, array $unitNames): array
    {
        $from = self::nullableNumber($range['age_from'] ?? null);
        $to = self::nullableNumber($range['age_to'] ?? null);
        if ($from === null && $to === null) {
            return [0.0, INF];
        }
        $unitId = self::nullableId($range['age_unit_id_fk'] ?? null);
        $unit = self::normalize($unitNames[$unitId] ?? '');
        $factor = match ($unit) {
            'days', 'day' => 1,
            'months', 'month' => 30,
            'years', 'year' => 365,
            default => null,
        };
        // Unknown age units must not be assumed disjoint.
        if ($factor === null) {
            return [0.0, INF];
        }

        return [($from ?? 0) * $factor, $to === null ? INF : $to * $factor];
    }
}
