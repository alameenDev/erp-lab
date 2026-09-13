<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usage_count' => (int) ($this->usage_count ?? 0),
            'name' => $this->name,
            'shortcut' => $this->shortcut,
            'test_group_id_fk' => $this->test_group_id_fk,
            'test_group' => $this->testGroup?->group_name,
            'report_name' => $this->report_name,
            'order' => $this->order,
            'lab' => $this->lab?->name,
            'category_id_fk' => $this->category_id_fk,
            'category' => $this->category?->name ?? null,
            'test_duration' => $this->test_duration,
            'duration_unit' => $this->durationUnit?->unit,
            'duration_unit_id_fk' => $this->duration_unit_id_fk,
            'sample_id_fk' => $this->sample_id_fk,
            'sample_name' => $this->sample?->sample_name,
            'result_comments' => $this->result_comments,
            'is_special_test' => $this->is_special_test == 1,
            'content' => $this->content,
            'sub_tests' => $this->sub_tests,
            'interface_code' => $this->interface_code,
            'unit' => $this->unit,
            'is_contain_status' => $this->is_contain_status,
            'is_print_alone' => $this->is_print_alone,
            'price' => $this->price,
            'for_customer_price' => $this->for_customer_price ?? 0,
            'prices' => $this->price_list_rel->map(function ($price) {
                return [
                    'id' => $price->id,
                    'price_list_id' => $price->priceList?->id,
                    'price_list_title' => $price->priceList?->name,
                    'original_price' => $price->original_price,
                    'price_for_customer' => $price->price_for_customer - ($price->price_for_customer * ($price->priceList?->discount ?? 0) / 100),
                ];
            }),
            'result_type_name' => $this->resultType?->result_type_name,
            'result_type_id_fk' => $this->result_type_id_fk,
            'selction_type_options' => $this->selection_type_options,
            'test_reference_ranges' => $this->testReferenceRanges->map(function ($testReferenceRange) {
                return [
                    'test_reference_range_id' => $testReferenceRange?->id,
                    'from' => $testReferenceRange?->from,
                    'to' => $testReferenceRange?->to,
                    'gender_id_fk' => $testReferenceRange?->gender_id_fk,
                    'gender' => $testReferenceRange?->gender?->gender_type,
                    'age_from' => $testReferenceRange?->age_from,
                    'age_to' => $testReferenceRange?->age_to,
                    'age_unit_id_fk' => $testReferenceRange?->age_unit_id_fk,
                    'age_unit' => $testReferenceRange?->ageUnit?->unit_name,
                    'test_reference_options' => $testReferenceRange?->selection_type_options,
                    'notes' => $testReferenceRange?->notes,
                ];
            }),
            'questions' => $this->questions->unique()->map(function ($question) {
                return [
                    'id' => $question->id,
                    'question' => $question->question,
                    'answer_type' => $question->answerType?->answer,
                    'answer_type_selection_values' => $question->answer_type_selection_values,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
