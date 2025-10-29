<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('product'); // Route Model Binding param

        return [
            'name'          => ['sometimes','string','max:150'],
            'code'          => [
                'nullable','string','max:100',
                Rule::unique('products','code')->ignore($id),
            ],
            'max_merge_qty' => ['sometimes','integer','min:1','max:9999'],
            'spec'          => ['sometimes','nullable','string','max:100'],
            'description'   => ['sometimes','nullable','string'],
            'is_active'     => ['sometimes','boolean'],
        ];
    }
}
