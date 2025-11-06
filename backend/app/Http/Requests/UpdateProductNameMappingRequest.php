<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductNameMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        $mapping = $this->route('mapping');
        $mappingId = is_object($mapping) ? $mapping->id : $mapping;

        return [
            'channel_id'    => ['required','integer','exists:channels,id'],
            'listing_title' => ['required','string','max:255'],
            'option_title'  => ['nullable','string','max:255'],
            'description'   => ['nullable','string','max:255'],

            // 자신 제외 중복 방지
            'composite'     => [
                Rule::unique('product_name_mappings')->ignore($mappingId)->where(function ($q) use ($productId) {
                    return $q->where('channel_id', $this->input('channel_id'))
                        ->where('product_id', $productId)
                        ->where('listing_title', $this->input('listing_title'))
                        ->where('option_title', $this->input('option_title'));
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'channel_id.required'    => '채널을 선택하세요.',
            'channel_id.exists'      => '유효하지 않은 채널입니다.',
            'listing_title.required' => '채널 상품명을 입력하세요.',
            'description.max'        => '설명은 255자 이하입니다.',
            'composite.unique'       => '동일한 채널/상품/상품명/옵션명이 이미 등록되어 있습니다.',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        unset($data['composite']);
        return $data;
    }
}
