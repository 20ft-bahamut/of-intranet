<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 필요시 권한 로직 추가
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:100'],
            'is_excel_encrypted' => ['nullable', 'boolean'],
            'excel_data_start_row' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        // 문자열로 들어온 불리언/숫자 교정
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

    public function messages(): array
    {
        return [
            'name.required' => '채널명을 입력하세요.',
            'code.required' => '채널 코드를 입력하세요.',
            'excel_data_start_row.required' => '엑셀 시작행을 입력하세요.',
        ];
    }
}
