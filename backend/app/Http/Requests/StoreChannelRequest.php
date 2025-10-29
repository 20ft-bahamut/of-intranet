<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChannelRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['required','string','max:50','alpha_dash','unique:channels,code'],
            'name' => ['required','string','max:100'],
            'is_excel_encrypted' => ['sometimes','boolean'],
            'excel_data_start_row' => ['sometimes','integer','min:1','max:100'],
            'is_active' => ['sometimes','boolean'],
        ];
    }
}
