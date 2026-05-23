<?php

namespace App\Http\Requests\Api;

use App\Models\KnowledgeDocument;
use Illuminate\Foundation\Http\FormRequest;

class UploadKnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'file'     => KnowledgeDocument::uploadRules(),
            'title'    => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
        ];
    }
}
