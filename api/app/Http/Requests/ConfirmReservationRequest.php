<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmReservationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $cpf = preg_replace(
            '/\D/',
            '',
            (string) $this->input('cpf')
        );

        $this->merge([
            'cpf' => $cpf,

            'idempotency_key' =>
                $this->header('Idempotency-Key'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cpf' => [
                'required',
                'digits:11',
            ],

            'idempotency_key' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}
