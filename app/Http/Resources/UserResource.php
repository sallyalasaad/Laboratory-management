<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_verified' => $this->is_verified,

            // 🔥 هذا المهم
            'role' => $this->getRoleNames()->first(),

            'contract_start_date' => $this->contract_start_date,
            'contract_end_date' => $this->contract_end_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

        ];
    }
}
