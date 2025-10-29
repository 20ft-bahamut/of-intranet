<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChannelExcelValidationRuleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // 채널은 라우트에서 받음
            'cell_ref'       => ['required','regex:/^[A-Z]{1,3}[1-9][0-9]*$/'], // A1 ~ ZZZ999...
            'expected_label' => ['required','string','max:100'],
            'is_required'    => ['sometimes','boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'cell_ref.regex' => '셀 위치는 대문자 열 + 숫자 행(A1 형태)여야 합니다.',
        ];
    }
}
