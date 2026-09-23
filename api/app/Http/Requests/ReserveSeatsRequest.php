<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReserveSeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seat_ids' => [
                'required',
                'array',
                'min:1',
                'max:8',
            ],

            'seat_ids.*' => [
                'required',
                'integer',
                'distinct',
            ],
        ];
    }
}
