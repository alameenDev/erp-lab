<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Copies lab-scoped reference data (tests, test-groups, packages, categories,
 * samples, cultures, questions, antibiotics) from one lab to another.
 *
 * Behavior:
 *  - All work happens in one DB transaction. If anything fails, nothing copied.
 *  - Foreign-key references are remapped to the destination lab's new IDs.
 *  - Owned children (test_reference_range, attributes, comments, sub_tests, etc.)
 *    are copied alongside the parent.
 *  - M2M pivots (test_questions_rel, test_test_group_rel, test_package_rel,
 *    culture_package_rel) are rebuilt against the new IDs.
 *  - Deduplication: an existing destination row matching the same identifier
 *    (name+shortcut for tests, name for everything else) is reused, not
 *    duplicated. Idempotent — running the same copy twice is a no-op.
 *  - Dependencies are auto-included transitively: copying a test pulls its
 *    category, sample, and questions; copying a test-group pulls its tests
 *    and cultures (and their dependencies); copying a package pulls tests
 *    and cultures.
 */
class LabDataCopier
{
    /** @var int */
    private $fromLabId;

    /** @var int */
    private $toLabId;

    /**
     * Maps[type] = [oldId => newId] — populated as we copy each type.
     * Shared across methods so later copies can remap FKs to new IDs.
     */
    private $maps = [
        'category' => [],
        'sample' => [],
        'question' => [],
        'antibiotic' => [],
        'culture' => [],
        'test' => [],
        'test_group' => [],
        'package' => [],
    ];

    /** Per-type counters for the result summary. */
    private $stats = [
        'category' => ['copied' => 0, 'reused' => 0],
        'sample' => ['copied' => 0, 'reused' => 0],
        'question' => ['copied' => 0, 'reused' => 0],
        'antibiotic' => ['copied' => 0, 'reused' => 0],
        'culture' => ['copied' => 0, 'reused' => 0],
        'test' => ['copied' => 0, 'reused' => 0],
        'test_group' => ['copied' => 0, 'reused' => 0],
        'package' => ['copied' => 0, 'reused' => 0],
    ];

    public function __construct(int $fromLabId, int $toLabId)
    {
        $this->fromLabId = $fromLabId;
        $this->toLabId = $toLabId;
    }

    /**
     * Public entry point. $selection is [type => [ids]]. Returns the result map.
     */
    public function run(array $selection, bool $dryRun = false): array
    {
        if ($this->fromLabId === $this->toLabId) {
            throw new \InvalidArgumentException('Source and destination lab must differ');
        }

        // Compute the full dependency closure first — what gets pulled in by
        // the user's selection, transitively.
        $needed = $this->resolveDependencies($selection);

        if ($dryRun) {
            return $this->dryRunSummary($needed);
        }

        DB::transaction(function () use ($needed) {
            // Strict order: independents → cultures/tests → groups/packages
            if (! empty($needed['category'])) {
                $this->copyCategories($needed['category']);
            }
            if (! empty($needed['sample'])) {
                $this->copySamples($needed['sample']);
            }
            if (! empty($needed['question'])) {
                $this->copyQuestions($needed['question']);
            }
            if (! empty($needed['antibiotic'])) {
                $this->copyAntibiotics($needed['antibiotic']);
            }
            if (! empty($needed['culture'])) {
                $this->copyCultures($needed['culture']);
            }
            if (! empty($needed['test'])) {
                $this->copyTests($needed['test']);
            }
            if (! empty($needed['test_group'])) {
                $this->copyTestGroups($needed['test_group']);
            }
            if (! empty($needed['package'])) {
                $this->copyPackages($needed['package']);
            }
        });

        return [
            'stats' => $this->stats,
            'maps' => $this->maps,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dependency resolution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Walk the dependency tree from the user's selection. Returns the full
     * set of IDs that need to be copied (or reused) per type.
     */
    private function resolveDependencies(array $selection): array
    {
        $needed = [
            'category' => array_unique($selection['category'] ?? []),
            'sample' => array_unique($selection['sample'] ?? []),
            'question' => array_unique($selection['question'] ?? []),
            'antibiotic' => array_unique($selection['antibiotic'] ?? []),
            'culture' => array_unique($selection['culture'] ?? []),
            'test' => array_unique($selection['test'] ?? []),
            'test_group' => array_unique($selection['test_group'] ?? []),
            'package' => array_unique($selection['package'] ?? []),
        ];

        // Packages → pull in their tests and cultures
        if (! empty($needed['package'])) {
            $tests = DB::table('test_package_rel')
                ->whereIn('package_id_fk', $needed['package'])
                ->pluck('test_id_fk')->all();
            $cultures = DB::table('culture_package_rel')
                ->whereIn('package_id_fk', $needed['package'])
                ->pluck('culture_id_fk')->all();
            $needed['test'] = array_unique(array_merge($needed['test'], $tests));
            $needed['culture'] = array_unique(array_merge($needed['culture'], $cultures));
        }

        // TestGroups → pull in their tests (M2M) and any cultures pointing to
        // them via cultures.test_group_id_fk
        if (! empty($needed['test_group'])) {
            $tests = DB::table('test_test_group_rel')
                ->whereIn('test_group_id_fk', $needed['test_group'])
                ->pluck('test_id_fk')->all();
            // Legacy: some tests reference test_group via tests.test_group_id_fk too
            $legacyTests = DB::table('tests')
                ->whereIn('test_group_id_fk', $needed['test_group'])
                ->whereNull('deleted_at')
                ->pluck('id')->all();
            $cultures = DB::table('cultures')
                ->whereIn('test_group_id_fk', $needed['test_group'])
                ->whereNull('deleted_at')
                ->pluck('id')->all();
            $needed['test'] = array_unique(array_merge($needed['test'], $tests, $legacyTests));
            $needed['culture'] = array_unique(array_merge($needed['culture'], $cultures));
        }

        // Tests → pull in category, sample, questions (M2M)
        if (! empty($needed['test'])) {
            $rows = DB::table('tests')
                ->whereIn('id', $needed['test'])
                ->whereNull('deleted_at')
                ->get(['category_id_fk', 'sample_id_fk']);
            foreach ($rows as $r) {
                if ($r->category_id_fk) {
                    $needed['category'][] = $r->category_id_fk;
                }
                if ($r->sample_id_fk) {
                    $needed['sample'][] = $r->sample_id_fk;
                }
            }
            $questionIds = DB::table('test_questions_rel')
                ->whereIn('test_id_fk', $needed['test'])
                ->whereNull('deleted_at')
                ->pluck('question_id_fk')->all();
            $needed['question'] = array_unique(array_merge($needed['question'], $questionIds));
        }

        // Cultures → pull in sample, category
        if (! empty($needed['culture'])) {
            $rows = DB::table('cultures')
                ->whereIn('id', $needed['culture'])
                ->whereNull('deleted_at')
                ->get(['category_id_fk', 'sample_id_fk']);
            foreach ($rows as $r) {
                if ($r->category_id_fk) {
                    $needed['category'][] = $r->category_id_fk;
                }
                if ($r->sample_id_fk) {
                    $needed['sample'][] = $r->sample_id_fk;
                }
            }
        }

        // TestGroups → pull in category, sample (referenced from group itself)
        if (! empty($needed['test_group'])) {
            $rows = DB::table('test_groups')
                ->whereIn('id', $needed['test_group'])
                ->whereNull('deleted_at')
                ->get(['category_id_fk', 'sample_id_fk']);
            foreach ($rows as $r) {
                if ($r->category_id_fk) {
                    $needed['category'][] = $r->category_id_fk;
                }
                if ($r->sample_id_fk) {
                    $needed['sample'][] = $r->sample_id_fk;
                }
            }
        }

        foreach ($needed as $k => $v) {
            $needed[$k] = array_values(array_unique(array_filter($v)));
        }

        return $needed;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Per-type copy methods
    // ─────────────────────────────────────────────────────────────────────────

    private function copyCategories(array $ids): void
    {
        $rows = DB::table('categories')
            ->whereIn('id', $ids)
            ->where('lab_id_fk', $this->fromLabId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('categories')
                ->where('lab_id_fk', $this->toLabId)
                ->where('name', $row->name)
                ->whereNull('deleted_at')
                ->first();
            if ($existing) {
                $this->maps['category'][$row->id] = $existing->id;
                $this->stats['category']['reused']++;

                continue;
            }
            $newId = DB::table('categories')->insertGetId([
                'name' => $row->name,
                'lab_id_fk' => $this->toLabId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->maps['category'][$row->id] = $newId;
            $this->stats['category']['copied']++;
        }
    }

    private function copySamples(array $ids): void
    {
        $rows = DB::table('samples')
            ->whereIn('id', $ids)
            ->where('lab_id_fk', $this->fromLabId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('samples')
                ->where('lab_id_fk', $this->toLabId)
                ->where('sample_name', $row->sample_name)
                ->whereNull('deleted_at')
                ->first();
            if ($existing) {
                $this->maps['sample'][$row->id] = $existing->id;
                $this->stats['sample']['reused']++;

                continue;
            }
            $newId = DB::table('samples')->insertGetId([
                'sample_name' => $row->sample_name,
                'lab_id_fk' => $this->toLabId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->maps['sample'][$row->id] = $newId;
            $this->stats['sample']['copied']++;
        }
    }

    private function copyQuestions(array $ids): void
    {
        $rows = DB::table('patient_questions')
            ->whereIn('id', $ids)
            ->where('lab_id_fk', $this->fromLabId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('patient_questions')
                ->where('lab_id_fk', $this->toLabId)
                ->where('question', $row->question)
                ->whereNull('deleted_at')
                ->first();
            if ($existing) {
                $this->maps['question'][$row->id] = $existing->id;
                $this->stats['question']['reused']++;

                continue;
            }
            $newId = DB::table('patient_questions')->insertGetId([
                'question' => $row->question,
                'answer_type_id_fk' => $row->answer_type_id_fk,
                'answer_type_selection_values' => $row->answer_type_selection_values,
                'lab_id_fk' => $this->toLabId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->maps['question'][$row->id] = $newId;
            $this->stats['question']['copied']++;
        }
    }

    private function copyAntibiotics(array $ids): void
    {
        $rows = DB::table('antibiotics')
            ->whereIn('id', $ids)
            ->where('lab_id', $this->fromLabId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('antibiotics')
                ->where('lab_id', $this->toLabId)
                ->where('scientific_name', $row->scientific_name)
                ->where('short_name', $row->short_name)
                ->whereNull('deleted_at')
                ->first();
            if ($existing) {
                $this->maps['antibiotic'][$row->id] = $existing->id;
                $this->stats['antibiotic']['reused']++;

                continue;
            }
            $newId = DB::table('antibiotics')->insertGetId([
                'scientific_name' => $row->scientific_name,
                'common_name' => $row->common_name,
                'short_name' => $row->short_name,
                'lab_id' => $this->toLabId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->maps['antibiotic'][$row->id] = $newId;
            $this->stats['antibiotic']['copied']++;
        }
    }

    private function copyCultures(array $ids): void
    {
        $rows = DB::table('cultures')
            ->whereIn('id', $ids)
            ->where('lab_id_fk', $this->fromLabId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('cultures')
                ->where('lab_id_fk', $this->toLabId)
                ->where('name', $row->name)
                ->whereNull('deleted_at')
                ->first();
            if ($existing) {
                $this->maps['culture'][$row->id] = $existing->id;
                $this->stats['culture']['reused']++;
                $this->copyCultureAttributes($row->id, $existing->id);

                continue;
            }
            $newId = DB::table('cultures')->insertGetId([
                'name' => $row->name,
                'precautions' => $row->precautions,
                'result_comments' => $row->result_comments,
                'category_id_fk' => $this->maps['category'][$row->category_id_fk] ?? null,
                'lab_id_fk' => $this->toLabId,
                'price' => $row->price,
                'price_for_customer' => $row->price_for_customer,
                'sample_id_fk' => $this->maps['sample'][$row->sample_id_fk] ?? null,
                'test_duration' => $row->test_duration,
                'duration_unit_id_fk' => $row->duration_unit_id_fk,
                // test_group_id_fk filled in copyTestGroups (after groups exist)
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->maps['culture'][$row->id] = $newId;
            $this->stats['culture']['copied']++;
            $this->copyCultureAttributes($row->id, $newId);
        }
    }

    private function copyCultureAttributes(int $oldCultureId, int $newCultureId): void
    {
        // Skip if destination already has attributes for this culture (dedup)
        $existing = DB::table('attributes')
            ->where('culture_id_fk', $newCultureId)
            ->whereNull('deleted_at')
            ->exists();
        if ($existing) {
            return;
        }

        $rows = DB::table('attributes')
            ->where('culture_id_fk', $oldCultureId)
            ->whereNull('deleted_at')
            ->get();
        foreach ($rows as $r) {
            DB::table('attributes')->insert([
                'name' => $r->name,
                'order' => $r->order,
                'result_type_id_fk' => $r->result_type_id_fk,
                'culture_id_fk' => $newCultureId,
                'selection_type_options' => $r->selection_type_options,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function copyTests(array $ids): void
    {
        $rows = DB::table('tests')
            ->whereIn('id', $ids)
            ->where('lab_id_fk', $this->fromLabId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('tests')
                ->where('lab_id_fk', $this->toLabId)
                ->where('name', $row->name)
                ->where('shortcut', $row->shortcut)
                ->whereNull('deleted_at')
                ->first();
            if ($existing) {
                $this->maps['test'][$row->id] = $existing->id;
                $this->stats['test']['reused']++;
                $this->copyTestQuestions($row->id, $existing->id);
                $this->copyTestReferenceRanges($row->id, $existing->id);

                continue;
            }
            $newId = DB::table('tests')->insertGetId([
                'name' => $row->name,
                'lab_id_fk' => $this->toLabId,
                'shortcut' => $row->shortcut,
                'report_name' => $row->report_name,
                'interface_code' => $row->interface_code,
                'unit' => $row->unit,
                'order' => $row->order,
                'category_id_fk' => $this->maps['category'][$row->category_id_fk] ?? null,
                'sample_id_fk' => $this->maps['sample'][$row->sample_id_fk] ?? null,
                'test_duration' => $row->test_duration,
                'duration_unit_id_fk' => $row->duration_unit_id_fk,
                'result_type_id_fk' => $row->result_type_id_fk,
                'selection_type_options' => $row->selection_type_options,
                'is_contain_status' => $row->is_contain_status,
                'price' => $row->price,
                'for_customer_price' => $row->for_customer_price,
                'is_print_alone' => $row->is_print_alone,
                'result_comments' => $row->result_comments,
                'is_special_test' => $row->is_special_test,
                'content' => $row->content,
                'sub_tests' => $row->sub_tests,
                // test_group_id_fk filled in copyTestGroups
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->maps['test'][$row->id] = $newId;
            $this->stats['test']['copied']++;
            $this->copyTestQuestions($row->id, $newId);
            $this->copyTestReferenceRanges($row->id, $newId);
        }
    }

    private function copyTestQuestions(int $oldTestId, int $newTestId): void
    {
        $existingQuestionIds = DB::table('test_questions_rel')
            ->where('test_id_fk', $newTestId)
            ->whereNull('deleted_at')
            ->pluck('question_id_fk')->all();

        $rows = DB::table('test_questions_rel')
            ->where('test_id_fk', $oldTestId)
            ->whereNull('deleted_at')
            ->get();
        foreach ($rows as $r) {
            $newQid = $this->maps['question'][$r->question_id_fk] ?? null;
            if (! $newQid || in_array($newQid, $existingQuestionIds)) {
                continue;
            }
            DB::table('test_questions_rel')->insert([
                'lab_id_fk' => $this->toLabId,
                'test_id_fk' => $newTestId,
                'question_id_fk' => $newQid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function copyTestReferenceRanges(int $oldTestId, int $newTestId): void
    {
        // If destination already has reference ranges for this test, leave them alone
        $hasExisting = DB::table('test_reference_range')
            ->where('test_id', $newTestId)
            ->whereNull('deleted_at')
            ->exists();
        if ($hasExisting) {
            return;
        }

        $rows = DB::table('test_reference_range')
            ->where('test_id', $oldTestId)
            ->whereNull('deleted_at')
            ->get();
        foreach ($rows as $r) {
            DB::table('test_reference_range')->insert([
                'lab_id_fk' => $this->toLabId,
                'test_id' => $newTestId,
                'gender_id_fk' => $r->gender_id_fk,
                'age_unit_id_fk' => $r->age_unit_id_fk,
                'age_from' => $r->age_from,
                'age_to' => $r->age_to,
                'from' => $r->from,
                'to' => $r->to,
                'notes' => $r->notes,
                'selection_type_options' => $r->selection_type_options,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function copyTestGroups(array $ids): void
    {
        $rows = DB::table('test_groups')
            ->whereIn('id', $ids)
            ->where('lab_id_fk', $this->fromLabId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('test_groups')
                ->where('lab_id_fk', $this->toLabId)
                ->where('group_name', $row->group_name)
                ->whereNull('deleted_at')
                ->first();
            if ($existing) {
                $this->maps['test_group'][$row->id] = $existing->id;
                $this->stats['test_group']['reused']++;
                $this->copyTestGroupRels($row->id, $existing->id);
                $this->copyTestGroupComments($row->id, $existing->id);
                $this->reattachCulturesToGroup($row->id, $existing->id);

                continue;
            }
            $payload = [
                'group_name' => $row->group_name,
                'lab_id_fk' => $this->toLabId,
                'category_id_fk' => $this->maps['category'][$row->category_id_fk] ?? null,
                'shortcut' => $row->shortcut,
                'sample_id_fk' => $this->maps['sample'][$row->sample_id_fk] ?? null,
                'original_price' => $row->original_price,
                'for_customer_price' => $row->for_customer_price,
                'test_duration' => $row->test_duration,
                'duration_unit_id_fk' => $row->duration_unit_id_fk,
                'precautions' => $row->precautions,
                'is_print_alone' => $row->is_print_alone,
                'result_comments' => $row->result_comments,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            // formula column was added later; tolerate envs without it
            if (property_exists($row, 'formula')) {
                $payload['formula'] = $row->formula;
            }
            $newId = DB::table('test_groups')->insertGetId($payload);
            $this->maps['test_group'][$row->id] = $newId;
            $this->stats['test_group']['copied']++;
            $this->copyTestGroupRels($row->id, $newId);
            $this->copyTestGroupComments($row->id, $newId);
            $this->reattachCulturesToGroup($row->id, $newId);
        }
    }

    private function copyTestGroupRels(int $oldGroupId, int $newGroupId): void
    {
        $existing = DB::table('test_test_group_rel')
            ->where('test_group_id_fk', $newGroupId)
            ->pluck('test_id_fk')->all();
        $rows = DB::table('test_test_group_rel')
            ->where('test_group_id_fk', $oldGroupId)
            ->get();
        foreach ($rows as $r) {
            $newTestId = $this->maps['test'][$r->test_id_fk] ?? null;
            if (! $newTestId || in_array($newTestId, $existing)) {
                continue;
            }
            DB::table('test_test_group_rel')->insert([
                'test_id_fk' => $newTestId,
                'test_group_id_fk' => $newGroupId,
                'order' => $r->order ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function copyTestGroupComments(int $oldGroupId, int $newGroupId): void
    {
        $hasExisting = DB::table('test_groups_comment')
            ->where('test_group_id_fk', $newGroupId)
            ->whereNull('deleted_at')
            ->exists();
        if ($hasExisting) {
            return;
        }

        $rows = DB::table('test_groups_comment')
            ->where('test_group_id_fk', $oldGroupId)
            ->whereNull('deleted_at')
            ->get();
        foreach ($rows as $r) {
            DB::table('test_groups_comment')->insert([
                'test_group_id_fk' => $newGroupId,
                'lab_id_fk' => $this->toLabId,
                'comment' => $r->comment,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Cultures can reference a test_group via cultures.test_group_id_fk. After
     * groups are copied, link new cultures to the new group ID.
     */
    private function reattachCulturesToGroup(int $oldGroupId, int $newGroupId): void
    {
        $sourceCultures = DB::table('cultures')
            ->where('test_group_id_fk', $oldGroupId)
            ->whereNull('deleted_at')
            ->pluck('id')->all();
        foreach ($sourceCultures as $oldCid) {
            $newCid = $this->maps['culture'][$oldCid] ?? null;
            if (! $newCid) {
                continue;
            }
            DB::table('cultures')
                ->where('id', $newCid)
                ->update(['test_group_id_fk' => $newGroupId, 'updated_at' => now()]);
        }
    }

    private function copyPackages(array $ids): void
    {
        $rows = DB::table('packages')
            ->whereIn('id', $ids)
            ->where('lab_id_fk', $this->fromLabId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('packages')
                ->where('lab_id_fk', $this->toLabId)
                ->where('name', $row->name)
                ->whereNull('deleted_at')
                ->first();
            if ($existing) {
                $this->maps['package'][$row->id] = $existing->id;
                $this->stats['package']['reused']++;
                $this->copyPackageRels($row->id, $existing->id);

                continue;
            }
            $payload = [
                'name' => $row->name,
                'lab_id_fk' => $this->toLabId,
                'shortcut' => $row->shortcut,
                'price' => $row->price,
                'is_constant_price' => $row->is_constant_price ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (property_exists($row, 'formula')) {
                $payload['formula'] = $row->formula;
            }
            $newId = DB::table('packages')->insertGetId($payload);
            $this->maps['package'][$row->id] = $newId;
            $this->stats['package']['copied']++;
            $this->copyPackageRels($row->id, $newId);
        }
    }

    private function copyPackageRels(int $oldPackageId, int $newPackageId): void
    {
        // tests
        $existingTests = DB::table('test_package_rel')
            ->where('package_id_fk', $newPackageId)
            ->pluck('test_id_fk')->all();
        $testRows = DB::table('test_package_rel')
            ->where('package_id_fk', $oldPackageId)
            ->get();
        foreach ($testRows as $r) {
            $newTestId = $this->maps['test'][$r->test_id_fk] ?? null;
            if (! $newTestId || in_array($newTestId, $existingTests)) {
                continue;
            }
            DB::table('test_package_rel')->insert([
                'test_id_fk' => $newTestId,
                'package_id_fk' => $newPackageId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // cultures
        $existingCultures = DB::table('culture_package_rel')
            ->where('package_id_fk', $newPackageId)
            ->pluck('culture_id_fk')->all();
        $cultureRows = DB::table('culture_package_rel')
            ->where('package_id_fk', $oldPackageId)
            ->get();
        foreach ($cultureRows as $r) {
            $newCid = $this->maps['culture'][$r->culture_id_fk] ?? null;
            if (! $newCid || in_array($newCid, $existingCultures)) {
                continue;
            }
            DB::table('culture_package_rel')->insert([
                'culture_id_fk' => $newCid,
                'package_id_fk' => $newPackageId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dry run summary
    // ─────────────────────────────────────────────────────────────────────────

    private function dryRunSummary(array $needed): array
    {
        $summary = [];
        foreach ($needed as $type => $ids) {
            if (empty($ids)) {
                continue;
            }
            [$table, $labCol, $matchCols] = $this->dryRunMeta($type);
            $sourceCount = DB::table($table)
                ->whereIn('id', $ids)
                ->where($labCol, $this->fromLabId)
                ->whereNull('deleted_at')
                ->count();
            // How many of those will be reused vs newly inserted?
            $reuse = 0;
            $sourceRows = DB::table($table)
                ->whereIn('id', $ids)
                ->where($labCol, $this->fromLabId)
                ->whereNull('deleted_at')
                ->get($matchCols);
            foreach ($sourceRows as $sr) {
                $q = DB::table($table)->where($labCol, $this->toLabId)->whereNull('deleted_at');
                foreach ($matchCols as $col) {
                    $q->where($col, $sr->$col);
                }
                if ($q->exists()) {
                    $reuse++;
                }
            }
            $summary[$type] = [
                'count' => $sourceCount,
                'reuse_existing' => $reuse,
                'will_create' => $sourceCount - $reuse,
            ];
        }

        return ['summary' => $summary];
    }

    private function dryRunMeta(string $type): array
    {
        switch ($type) {
            case 'category':   return ['categories', 'lab_id_fk', ['name']];
            case 'sample':     return ['samples', 'lab_id_fk', ['sample_name']];
            case 'question':   return ['patient_questions', 'lab_id_fk', ['question']];
            case 'antibiotic': return ['antibiotics', 'lab_id', ['scientific_name', 'short_name']];
            case 'culture':    return ['cultures', 'lab_id_fk', ['name']];
            case 'test':       return ['tests', 'lab_id_fk', ['name', 'shortcut']];
            case 'test_group': return ['test_groups', 'lab_id_fk', ['group_name']];
            case 'package':    return ['packages', 'lab_id_fk', ['name']];
        }
        throw new \InvalidArgumentException("Unknown type: $type");
    }
}
