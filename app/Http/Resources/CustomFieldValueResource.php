<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomFieldValueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'custom_field_id' => $this->custom_field_id,
            'custom_field' => new CustomFieldResource($this->whenLoaded('customField')),
            'sale_id' => $this->sale_id,
            'sale_item_id' => $this->sale_item_id,
            'value' => $this->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
