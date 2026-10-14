<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdjustProductStockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ! $this->user()->isDriver();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'adjustment' => [
                'required',
                'integer',
                'not_in:0',
                'min:'.-$this->route('product')->quantity,
                'max:1000000',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'adjustment.min' => "Cannot remove more than the {$this->route('product')->quantity} units in stock.",
            'adjustment.not_in' => 'Enter a quantity to add or remove.',
        ];
    }
}
