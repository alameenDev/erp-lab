<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
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
            'code' => $this->code,
            'title' => $this->title?->title,
            'title_id_fk' => $this->title_id_fk,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'role' => $this->user?->role?->name,
            'barcode' => $this->barcode,
            'phone_number' => $this->user?->phone_number,
            'lab' => $this->lab?->name,
            'parent_id' => $this->parent_id,
            'image' => $this->image,
            'lab_card' => $this->lab_card,
            'address' => $this->user?->address,
            'nationality' => $this->nationality?->country_name,
            'dob' => $this->dob,
            'gender' => $this->gender?->gender_type,
            'gender_id_fk' => $this->gender_id_fk,
            'age' => $this->age,
            'age_unit' => $this->ageUnit?->unit_name,
            'age_unit_id_fk' => $this->age_unit_id_fk,
            'passport_no' => $this->passport_no,
            'national_id_no' => $this->national_id_no,
            'contract' => $this->contract,
            'contract_id_fk' => $this->contract_id_fk,
        ];
    }
}
