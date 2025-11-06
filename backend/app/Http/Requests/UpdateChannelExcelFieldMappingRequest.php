<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelExcelFieldMappingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'selector_type'  => ['sometimes','required','in:col_ref,header_text,regex,expr'],
            'selector_value' => ['sometimes','required','string','max:255'],
            'options'        => ['nullable','array'],
        ];
    }
}
