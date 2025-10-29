<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductNameMappingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'channel_id'    => $this->channel_id,
            'product_id'    => $this->product_id,
            'listing_title' => $this->listing_title,
            'option_title'  => $this->option_title,
            'description'   => $this->description,
            'created_at'    => optional($this->created_at)->toISOString(),
            'updated_at'    => optional($this->updated_at)->toISOString(),
        ];
    }
}
