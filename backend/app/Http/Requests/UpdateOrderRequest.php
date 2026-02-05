<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 필요 시 정책 연결
    }

    public function rules(): array
    {
        return [
            'admin_memo'       => ['nullable','string','max:255'],
            'tracking_no'      => ['nullable','string','max:64'],
            'status_std'       => ['nullable','string','max:50'],
            // ✅ 고객요청사항(배송요구사항)
            'shipping_request' => ['nullable','string','max:255'],
        ];
    }

    public function prepareForValidation(): void
    {
        foreach (['admin_memo','tracking_no','status_std','shipping_request'] as $k) {
            if ($this->has($k)) {
                $v = trim((string) $this->input($k));
                $this->merge([$k => ($v === '' ? null : $v)]);
            }
        }
    }

}
