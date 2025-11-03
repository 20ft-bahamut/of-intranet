<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 같은 규칙이지만 일부는 sometimes
        return [
            'name' => ['sometimes','required','string','max:150'],
            'code' => ['sometimes','required','string','max:100'],
            'is_excel_encrypted' => ['nullable','boolean'],
            'excel_data_start_row' => ['sometimes','required','integer','min:1'],
            'is_active' => ['nullable','boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('is_excel_encrypted')) {
            $this->merge(['is_excel_encrypted' => filter_var($this->input('is_excel_encrypted'), FILTER_VALIDATE_BOOLEAN)]);
        }
        if ($this->has('is_active')) {
            $this->merge(['is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN)]);
        }
        if ($this->has('excel_data_start_row')) {
            $this->merge(['excel_data_start_row' => (int) $this->input('excel_data_start_row')]);
        }
    }
}
