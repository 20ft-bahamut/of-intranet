<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChannelExcelTransformProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tracking_col_ref' => ['required','regex:/^[A-Z]{1,3}(?::[A-Z]{1,3})?$/'],
            'courier_name'     => ['nullable','string','max:50','required_without:courier_code'],
            'courier_code'     => ['nullable','string','max:20','required_without:courier_name'],
            'template_notes'   => ['nullable','string','max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'tracking_col_ref.required' => '송장번호 열 위치는 필수입니다.',
            'tracking_col_ref.regex'    => '열 위치는 G 또는 G:G 형식으로 입력하세요.',
            'courier_name.required_without' => '택배사명 또는 택배사 코드 중 하나는 반드시 입력하세요.',
            'courier_code.required_without' => '택배사명 또는 택배사 코드 중 하나는 반드시 입력하세요.',
        ];
    }
}
