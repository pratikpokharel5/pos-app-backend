<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'business_name' => $this->business->name,
            'logo' => $this->business->logo,
            'address' => $this->business->address,
            'phone' => $this->business->phone,
            'email' => $this->business->email,
            'tax_enabled' => $this->tax_enabled,
            'default_tax_rate' => $this->default_tax_rate,
            'online_payment_enabled' => $this->online_payment_enabled,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
