<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:180'],
            'starts_from' => ['nullable', 'date'],
            'starts_to' => ['nullable', 'date', 'after_or_equal:starts_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
