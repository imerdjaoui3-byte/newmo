<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDriverOrderStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only the assigned driver may update the order, and only while it is still open.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order->driver_id === $this->user()->id && $order->isOpen();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['in_route', 'done', 'no_show'])],
            'payment_method' => ['nullable', 'required_if:status,done', Rule::in(['emt', 'cash'])],
            'payment_reference' => ['nullable', 'required_if:payment_method,emt', 'string', 'max:255'],
            'items' => ['exclude_unless:status,done', 'required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
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
                if ($validator->errors()->isNotEmpty() || $this->input('status') !== 'done') {
                    return;
                }

                $this->ensureDriverHasStock($validator);
            },
        ];
    }

    /**
     * Reject sold quantities beyond what is left of the driver's stock for today.
     */
    private function ensureDriverHasStock(Validator $validator): void
    {
        $driver = $this->user();
        $loads = $driver->driverLoads()->whereDate('load_date', today())->with('product')->get()->keyBy('product_id');
        $soldToday = $driver->soldQuantitiesOn(today());

        foreach ($this->input('items') as $index => $item) {
            $load = $loads->get($item['product_id']);

            if (! $load) {
                $validator->errors()->add("items.{$index}.product_id", 'That product is not in your stock today.');

                continue;
            }

            $remaining = $load->remaining($soldToday[$load->product_id] ?? 0);

            if ((int) $item['quantity'] > $remaining) {
                $validator->errors()->add(
                    "items.{$index}.quantity",
                    "You only have {$remaining} {$load->product->name} left.",
                );
            }
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.required_if' => 'Choose whether the order was paid by EMT or cash.',
            'payment_reference.required_if' => 'Enter the EMT transaction ID.',
            'items.required' => 'Select the products that were sold.',
            'items.*.product_id.distinct' => 'Each product can only be listed once.',
        ];
    }
}
