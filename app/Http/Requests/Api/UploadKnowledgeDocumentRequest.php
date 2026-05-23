<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadKnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'file'     => 'required|file|mimes:pdf,docx,doc,txt|max:51200',
            'title'    => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
        ];
    }
}
