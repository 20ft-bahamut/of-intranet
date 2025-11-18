<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommitChannelOrdersRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'upload_path' => ['required','string','max:500'], // 저장된 파일의 절대/실경로
            'password'    => ['nullable','string','max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'upload_path.required' => '업로드된 파일 경로가 필요합니다.',
        ];
    }
}
