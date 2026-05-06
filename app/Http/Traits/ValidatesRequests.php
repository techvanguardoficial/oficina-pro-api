<?php

namespace App\Http\Traits;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;

trait ValidatesRequests
{
    /**
     * Handle failed validation and return a JSON response
     */
    protected function failedValidation(Validator $validator): JsonResponse
    {
        return response()->json([
            'error' => true,
            'message' => 'Validation failed',
            'code' => 'VALIDATION_ERROR',
            'details' => $validator->errors(),
        ], 422);
    }

    /**
     * Custom validation error message for a specific field
     */
    protected function getCustomErrorMessage(string $field, string $rule): ?string
    {
        $messages = [
            'email.unique' => 'Este email já está registrado.',
            'email.email' => 'Email inválido.',
            'email.required' => 'Email é obrigatório.',
            'password.min' => 'Senha deve ter no mínimo 8 caracteres.',
            'password.required' => 'Senha é obrigatória.',
            'password.confirmed' => 'As senhas não correspondem.',
            'cpf_cnpj.unique' => 'Este CPF/CNPJ já está registrado.',
            'placa.unique' => 'Esta placa já está registrada.',
            'placa.max' => 'Placa não pode ter mais de 7 caracteres.',
            'value.min' => 'Valor deve ser maior que 0.',
            'quantity.min' => 'Quantidade deve ser maior que 0.',
            'year.min' => 'Ano deve ser 1900 ou posterior.',
            'year.max' => 'Ano não pode ser no futuro.',
        ];

        return $messages["{$field}.{$rule}"] ?? null;
    }
}
