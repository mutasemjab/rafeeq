<?php

namespace App\Http\Requests\Api;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'child_id' => 'nullable|exists:children,id',
            'title'    => 'nullable|string|max:255',
            'source'   => ['nullable', Rule::in(Conversation::acceptedInputSources())],
        ];
    }
}
