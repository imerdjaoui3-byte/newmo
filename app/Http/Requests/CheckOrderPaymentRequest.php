<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CheckOrderPaymentRequest extends FormRequest
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
        return [];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->route('order')->awaitsPaymentCheck()) {
                    $validator->errors()->add('payment', 'Only delivered EMT orders with a reference can be checked.');
                } elseif (! $this->user()->mailbox) {
                    $validator->errors()->add('payment', 'Sign in with Google to connect your inbox before checking payments.');
                }
            },
        ];
    }
}
