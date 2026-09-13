<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Cache price calculations for the request lifecycle
        static $calculatedPrices = [];
        $price = null;

        foreach ($this->whenLoaded('tests') as $test) {
            $price += $test->price;
        }

        foreach ($this->whenLoaded('cultures') as $culture) {
            $price += $culture->price;
        }

        $priceListData = $this->price_list_rel->map(function ($price) use (&$calculatedPrices) {
            $priceListId = $price->priceList?->id;

            if (! isset($calculatedPrices[$this->id][$priceListId])) {
                $discount = $price->priceList?->discount ?? 0;
                $priceForCustomer = $price->price_for_customer;
                $calculatedPrices[$this->id][$priceListId] = $priceForCustomer - ($priceForCustomer * ($discount / 100));
            }

            return [
                'id' => $price->id,
                'price_list_id' => $priceListId,
                'price_list_name' => $price->priceList?->name,
                'original_price' => $price->original_price,
                'price_for_customer' => $calculatedPrices[$this->id][$priceListId],
            ];
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'shortcut' => $this->shortcut,
            'lab' => $this->lab?->name,
            'price' => $this->price ?? $price,
            'prices' => $priceListData,
            'is_constant_price' => $this->is_constant_price,
            'formula' => $this->formula,
            'tests' => TestResource::collection($this->whenLoaded('tests')),
            'cultures' => CultureResource::collection($this->whenLoaded('cultures')),
        ];
    }
}
