<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductNameMappingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'channel_id'    => ['required','integer','exists:channels,id'],
            'listing_title' => ['required','string','max:255'],
            'option_title'  => ['nullable','string','max:255'],
            'description'   => ['nullable','string','max:255'],
        ];
    }
}
