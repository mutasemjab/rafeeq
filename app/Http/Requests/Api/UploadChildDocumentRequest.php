<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadChildDocumentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'file'        => 'required|file|mimes:pdf,docx,doc,txt,jpg,jpeg,png|max:20480',
            'title'       => 'nullable|string|max:255',
            'document_type' => 'nullable|string|max:100',
        ];
    }
}
