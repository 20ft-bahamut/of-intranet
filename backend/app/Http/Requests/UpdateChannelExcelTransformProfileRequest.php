<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelExcelTransformProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_col_ref' => ['sometimes','required','string','regex:/^[A-Z]{1,3}(?::[A-Z]{1,3})?$/'],
            'courier_name'     => ['nullable','string','max:100','required_without:courier_code'],
            'courier_code'     => ['nullable','string','max:50','required_without:courier_name'],
            'template_notes'   => ['nullable','string','max:1000'],
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('tracking_col_ref')) {
            $this->merge([
                'tracking_col_ref' => strtoupper(str_replace([' ', "\t"], '', (string) $this->input('tracking_col_ref')))
            ]);
        }
        foreach (['courier_name','courier_code','template_notes'] as $k) {
            if ($this->has($k)) {
                $this->merge([$k => trim((string) $this->input($k))]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'tracking_col_ref.required' => '송장 입력 컬럼을 지정하세요.',
            'tracking_col_ref.regex'    => '컬럼 표기는 예: "G" 또는 "G:G" 형식이어야 합니다.',
            'courier_name.required_without' => '택배사명 또는 택배사 코드를 하나 이상 입력하세요.',
            'courier_code.required_without' => '택배사명 또는 택배사 코드를 하나 이상 입력하세요.',
        ];
    }
}
