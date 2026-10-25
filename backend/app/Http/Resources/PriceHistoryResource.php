<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gadget_id' => $this->gadget_id,
            'price' => (int) $this->price,
            'recorded_at' => $this->recorded_at?->toISOString(),
        ];
    }
}
