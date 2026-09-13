<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SampleCollectorResource extends JsonResource
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
            'id' => $this?->id,
            'name' => $this?->name,
            'image' => $this?->image,
            'phone_number' => $this?->phone_number,
            'email' => $this?->email,
            'role' => $this?->role?->name,
            'lab' => $this?->lab?->name,
            'address' => $this?->address,
            'signiture' => $this?->signiture,
            'commission' => $this?->role_id == 6 ? $this?->referals?->commission : $this?->lab?->discount_percentage,
            'discount_percentage' => $this?->role_id == 6 ? $this?->referals?->commission : $this?->lab?->discount_percentage,

        ];
    }
}
