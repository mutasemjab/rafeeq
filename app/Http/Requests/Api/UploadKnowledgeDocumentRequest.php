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
            'topics' => 'nullable|array|max:20',
            'topics.*' => 'string|max:100',
            'problem_types' => 'nullable|array|max:20',
            'problem_types.*' => 'string|max:100',
            'age_min_months' => 'nullable|integer|min:0|max:300',
            'age_max_months' => 'nullable|integer|min:0|max:300|gte:age_min_months',
            'audience' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:12',
            'evidence_level' => 'nullable|string|max:50',
            'publisher' => 'nullable|string|max:255',
            'source_url' => 'nullable|url|max:2000',
            'published_at' => 'nullable|date',
            'reviewed_at' => 'nullable|date',
            'is_approved' => 'nullable|boolean',
        ];
    }
}
