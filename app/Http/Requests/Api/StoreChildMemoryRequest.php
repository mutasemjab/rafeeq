<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreChildMemoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'key'     => 'required|string|max:100',
            'content' => 'required|string',
            'child_id' => 'required|exists:children,id',
        ];
    }
}
