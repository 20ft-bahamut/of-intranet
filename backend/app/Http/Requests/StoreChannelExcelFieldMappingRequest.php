<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChannelExcelFieldMappingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'field_key'      => ['required','string','max:50','regex:/^[a-z0-9_\.:-]+$/'],
            'selector_type'  => ['required','in:col_ref,header_text,regex,expr'],
            'selector_value' => ['required','string','max:255'],
            'options'        => ['nullable','array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('field_key')) {
            $this->merge(['field_key' => mb_strtolower($this->input('field_key'))]);
        }
    }

    public function messages(): array
    {
        return [
            'field_key.required' => '필드 키는 필수입니다.',
            'selector_type.in'   => 'selector_type은 col_ref, header_text, regex, expr 중 하나여야 합니다.',
        ];
    }
}
