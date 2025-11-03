<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChannelExcelValidationRuleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'channel_id'     => $this->channel_id,
            'cell_ref'       => $this->cell_ref,
            'expected_label' => $this->expected_label,
            'is_required'    => (bool) $this->is_required,
            'created_at'     => optional($this->created_at)->toIso8601String(),
            'updated_at'     => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
