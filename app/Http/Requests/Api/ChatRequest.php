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
            'extra_thinking' => 'sometimes|boolean',
            'feedback' => 'nullable|array:message_id,reason,comment',
            'feedback.message_id' => 'required_with:feedback|integer|min:1',
            'feedback.reason' => 'required_with:feedback|in:misunderstood,too_long,repetitive,impractical',
            'feedback.comment' => 'nullable|string|max:1000',
        ];
    }
}
