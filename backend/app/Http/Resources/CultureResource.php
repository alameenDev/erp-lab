<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CultureResource extends JsonResource
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
            'name' => $this->name,
            'category_id_fk' => $this->category_id_fk,
            'category' => $this->category?->name ?? null,
            'test_group_id_fk' => $this->test_group_id_fk,
            'test_group' => $this->test_group?->group_name,
            'precautions' => $this->precautions,
            'result_comments' => $this->result_comments,
            'lab' => $this->lab?->name,
            'price' => $this->price,
            'price_for_customer' => $this->price_for_customer,
            'prices' => $this->price_list_rel->map(function ($priceListRel) {
                return [
                    'id' => $priceListRel->id,
                    'price_list_id' => $priceListRel->priceList?->id,
                    'price_list_name' => $priceListRel->priceList?->name,
                    'original_price' => $priceListRel->original_price,
                    'price_for_customer' => $priceListRel->price_for_customer - ($priceListRel->price_for_customer * (($priceListRel->priceList?->discount ?? 0) / 100)),
                ];
            }),
            'sample' => $this->sample?->sample_name,
            'sample_id_fk' => $this->sample_id_fk,
            'test_duration' => $this->test_duration,
            'duration_unit' => $this->duration_unit?->unit,
            'duration_unit_id_fk' => $this->duration_unit_id_fk,
            'attribute' => $this->attribute->sortBy('order')->map(function ($attribute) {
                return [
                    'id' => $attribute?->id,
                    'attribute_name' => $attribute?->name,
                    'result_type' => $attribute?->resultType?->result_type_name,
                    'result_type_id_fk' => $attribute?->result_type_id_fk,
                    'selection_type_options' => $attribute?->selection_type_options,
                    'order' => $attribute?->order,

                ];
            })->values(),
        ];
    }
}
