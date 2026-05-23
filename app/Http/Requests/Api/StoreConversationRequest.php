<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'child_id' => 'nullable|exists:children,id',
            'title'    => 'nullable|string|max:255',
            'source'   => 'nullable|in:mobile,web,voice',
        ];
    }
}
