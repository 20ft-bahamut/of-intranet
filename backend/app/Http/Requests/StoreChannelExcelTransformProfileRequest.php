<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChannelExcelTransformProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tracking_col_ref' => ['required','regex:/^[A-Z]{1,3}(:[A-Z]{1,3})?$/'],
            'courier_name'     => ['required','string','max:50'],
            'courier_code'     => ['required','string','max:20'],
            'template_notes'   => ['sometimes','string','max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'tracking_col_ref.regex' => 'tracking_col_ref 형식은 G 또는 G:G, AA:AB 형태여야 합니다.',
        ];
    }
}
