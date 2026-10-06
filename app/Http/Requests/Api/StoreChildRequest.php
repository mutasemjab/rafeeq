<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreChildRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:100',
            'date_of_birth'     => 'required|date|before_or_equal:today',
            'country_of_birth' => 'nullable|string|max:100',
            'country_of_residence' => 'nullable|string|max:100',
            'gender'            => 'required|in:male,female',
            'diagnosis'         => 'nullable|string|max:255',
            'diagnosis_details' => 'nullable|string',
            'notes'             => 'nullable|string',
            'avatar'            => 'nullable|image|max:2048',
        ];
    }
}
