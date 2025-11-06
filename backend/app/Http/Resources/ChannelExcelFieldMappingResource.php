<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChannelExcelFieldMappingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'channel_id'     => $this->channel_id,
            'field_key'      => $this->field_key,
            'selector_type'  => $this->selector_type,
            'selector_value' => $this->selector_value,
            'options'        => $this->options,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
