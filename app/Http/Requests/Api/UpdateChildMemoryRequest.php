<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChildMemoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'key'     => 'sometimes|required|string|max:100',
            'content' => 'sometimes|required|string',
        ];
    }
}
