<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BackfillProductNameMappingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'mode' => ['nullable', Rule::in(['exact', 'title_only'])],
            'dry'  => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'mode.in' => 'mode 값은 exact 또는 title_only 만 가능합니다.',
        ];
    }

    public function mode(): string
    {
        return (string) $this->input('mode', 'exact');
    }

    public function dry(): bool
    {
        return (bool) $this->boolean('dry', false);
    }
}
