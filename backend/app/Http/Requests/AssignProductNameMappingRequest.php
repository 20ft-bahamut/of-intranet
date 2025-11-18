<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignProductNameMappingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'product_id는 필수입니다.',
            'product_id.exists'   => '존재하지 않는 상품입니다.',
        ];
    }

    public function productId(): int
    {
        return (int) $this->input('product_id');
    }
}
