<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seats' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'seats.*.sector' => [
                'sometimes',
                'string',
                'max:80',
            ],

            'seats.*.row_label' => [
                'required',
                'string',
                'max:20',
            ],

            'seats.*.number' => [
                'required',
                'string',
                'max:20',
            ],
        ];
    }
}
