<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChildRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'              => 'sometimes|required|string|max:100',
            'date_of_birth'     => 'sometimes|required|date|before:today',
            'gender'            => 'sometimes|required|in:male,female',
            'diagnosis'         => 'nullable|string|max:255',
            'diagnosis_details' => 'nullable|string',
            'notes'             => 'nullable|string',
            'avatar'            => 'nullable|image|max:2048',
        ];
    }
}
