<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductNameMappingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // unmapped 파라미터가 없으면 전체, '1'이면 미매핑, '0'이면 매핑됨
            'unmapped'   => ['nullable', 'in:0,1'],
            'channel_id' => ['nullable', 'integer', 'exists:by-channel,id'],
            'q'          => ['nullable', 'string', 'max:200'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** null | true | false */
    public function unmappedFilter(): ?bool
    {
        if (!$this->has('unmapped')) return null;   // 전체
        return $this->input('unmapped') === '1';    // '1' => 미매핑, '0' => 매핑됨
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', 20);
    }
}
