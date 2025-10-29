<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => ['required','string','max:150'],
            'code'          => ['nullable','string','max:100','unique:products,code'],
            'max_merge_qty' => ['required','integer','min:1','max:9999'],
            'spec'          => ['nullable','string','max:100'],
            'description'   => ['nullable','string'],
            'is_active'     => ['sometimes','boolean'],
        ];
    }
}
