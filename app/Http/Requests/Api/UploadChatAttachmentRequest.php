<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadChatAttachmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'file'            => 'required|file|mimes:pdf,docx,doc,txt,jpg,jpeg,png|max:20480',
            'conversation_id' => 'required|exists:conversations,id',
        ];
    }
}
