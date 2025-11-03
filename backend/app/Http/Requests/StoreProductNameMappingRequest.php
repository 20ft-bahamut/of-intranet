<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductNameMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // URL 바인딩된 product (모델 or id)
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        return [
            'channel_id'    => ['required','integer','exists:channels,id'],
            'listing_title' => ['required','string','max:255'],
            'option_title'  => ['required','string','max:255'],
            'description'   => ['nullable','string','max:255'],

            // (channel_id, product_id, listing_title, option_title) 유니크
            'composite'     => [
                Rule::unique('product_name_mappings')->where(function ($q) use ($productId) {
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
            'option_title.required'  => '채널 옵션명을 입력하세요.',
            'description.max'        => '설명은 255자 이하입니다.',
            'composite.unique'       => '동일한 채널/상품/상품명/옵션명이 이미 등록되어 있습니다.',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        // DB 입력 컬럼만 반환 (composite 가상필드는 제외)
        unset($data['composite']);
        return $data;
    }
}
