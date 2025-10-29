<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductNameMappingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'channel_id'    => ['sometimes','integer','exists:channels,id'],
            'listing_title' => ['sometimes','string','max:255'],
            'option_title'  => ['sometimes','nullable','string','max:255'],
            'description'   => ['sometimes','nullable','string','max:255'],
        ];
    }
}
