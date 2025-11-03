<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('product')?->id; // 경로 파라미터에서 ID 추출

        return [
            'name'           => ['sometimes','required','string','max:150'],
            'code'           => ['sometimes','required','string','max:100',"unique:products,code,{$id}"],
            'max_merge_qty'  => ['sometimes','required','integer','min:1'],
            'spec'           => ['nullable','string','max:100'],
            'description'    => ['nullable','string'],
            'is_active'      => ['nullable','boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN)]);
        }
        if ($this->has('max_merge_qty')) {
            $this->merge(['max_merge_qty' => (int) $this->input('max_merge_qty')]);
        }
    }

    public function messages(): array
    {
        return [
            'code.unique'            => '이미 존재하는 제품 코드입니다.',
            'name.required'          => '제품명을 입력하세요.',
            'name.max'               => '제품명은 150자 이하입니다.',
            'code.required'          => '제품 코드를 입력하세요.',
            'code.max'               => '제품 코드는 100자 이하입니다.',
            'max_merge_qty.required' => '최대 합포장개수를 입력하세요.',
            'max_merge_qty.integer'  => '최대 합포장개수는 정수여야 합니다.',
            'max_merge_qty.min'      => '최대 합포장개수는 1 이상이어야 합니다.',
            'spec.max'               => '스펙은 100자 이하입니다.',
        ];
    }
}
