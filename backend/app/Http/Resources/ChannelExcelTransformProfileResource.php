<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChannelExcelTransformProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'channel_id'       => $this->channel_id,
            'tracking_col_ref' => $this->tracking_col_ref,
            'courier_name'     => $this->courier_name,
            'courier_code'     => $this->courier_code,
            'template_notes'   => $this->template_notes,
            'created_at'       => optional($this->created_at)->toIso8601String(),
            'updated_at'       => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
