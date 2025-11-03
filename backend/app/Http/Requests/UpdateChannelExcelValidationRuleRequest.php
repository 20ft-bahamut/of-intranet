<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelExcelValidationRuleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cell_ref'       => ['sometimes','required','string','regex:/^[A-Z]{1,3}[1-9]\d{0,4}$/'],
            'expected_label' => ['sometimes','required','string','max:150'],
            'is_required'    => ['sometimes','required','boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('cell_ref')) {
            $this->merge(['cell_ref' => strtoupper(trim((string)$this->input('cell_ref')))]);
        }
        if ($this->has('is_required')) {
            $this->merge(['is_required' => filter_var($this->input('is_required'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    public function messages(): array
    {
        return [
            'cell_ref.regex'          => '셀 위치 형식이 올바르지 않습니다. (예: A1, AB10)',
            'expected_label.required' => '기대 라벨명을 입력하세요.',
            'is_required.boolean'     => '필수 여부 값이 올바르지 않습니다.',
        ];
    }
}
