<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductNameMappingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'channel_id'    => $this->channel_id,
            'channel_name'  => $this->whenLoaded('channel', fn() => $this->channel?->name),
            'listing_title' => $this->listing_title,
            'option_title'  => $this->option_title,
            'description'   => $this->description,
            'product_id'    => $this->product_id,
            'product_name'  => $this->whenLoaded('product', fn() => $this->product?->name),
            'product_code'  => $this->whenLoaded('product', fn() => $this->product?->code),
            'created_at'    => optional($this->created_at)->toDateTimeString(),
            'updated_at'    => optional($this->updated_at)->toDateTimeString(),
            'last_backfilled_at' => optional($this->last_backfilled_at)->format('Y-m-d H:i:s'), // ← 추가
        ];
    }
}
