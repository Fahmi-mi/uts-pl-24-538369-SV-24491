<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GadgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->category,
            'store' => $this->store,
            'product_url' => $this->product_url,
            'target_price' => (int) $this->target_price,
            'current_price' => (int) $this->current_price,
            'price_gap' => (int) ($this->current_price - $this->target_price),
            'target_reached' => (bool) ($this->current_price <= $this->target_price),
            'priority' => $this->priority,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
