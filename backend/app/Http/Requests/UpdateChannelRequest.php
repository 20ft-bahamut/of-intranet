<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChannelRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('channel'); // Route Model Binding 명과 일치해야 함

        return [
            'code' => [
                'sometimes','string','max:50','alpha_dash',
                Rule::unique('channels','code')->ignore($id),
            ],
            'name' => ['sometimes','string','max:100'],
            'is_excel_encrypted' => ['sometimes','boolean'],
            'excel_data_start_row' => ['sometimes','integer','min:1','max:100'],
            'is_active' => ['sometimes','boolean'],
        ];
    }
}
