<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string|max:4000',
            'language' => 'nullable|in:en,ar',
            'client_message_id' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/', 'required_if:async,true,1'],
            'async' => 'sometimes|boolean',
        ];
    }
}
