<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'location' => [
                'required',
                'string',
                'max:180',
            ],

            'starts_at' => [
                'required',
                'date',
                'after:now',
            ],

            'sales_start_at' => [
                'required',
                'date',
                'before:starts_at',
            ],

            'sales_end_at' => [
                'nullable',
                'date',
                'after:sales_start_at',
                'before_or_equal:starts_at',
            ],

            'status' => [
                'sometimes',
                Rule::enum(EventStatus::class),
            ],
        ];
    }
}
