<?php

namespace App\Http\Requests;

use App\Models\Driver;
use App\Models\DriverLoad;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class UpdateDriverLoadsRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
            'loads' => ['required', 'array'],
            'loads.*' => ['array'],
            'loads.*.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
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
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->ensureDriversAreActive($validator);
                $this->ensureLoadsCoverSales($validator);
                $this->ensureEnoughStock($validator);
            },
        ];
    }

    /**
     * Get the submitted quantities keyed by driver user ID, then product ID.
     *
     * @return array<int, array<int, int>>
     */
    public function loads(): array
    {
        return collect($this->input('loads'))
            ->map(fn (array $products): array => array_map(fn (?string $quantity): int => (int) $quantity, $products))
            ->all();
    }

    /**
     * Reject loads for users who are not active drivers.
     */
    private function ensureDriversAreActive(Validator $validator): void
    {
        $driverIds = array_keys($this->input('loads'));
        $activeCount = Driver::whereIn('user_id', $driverIds)->where('is_active', true)->count();

        if ($activeCount !== count($driverIds)) {
            $validator->errors()->add('loads', 'Stock can only be loaded onto active drivers.');
        }
    }

    /**
     * Reject loads lower than what the driver already sold or handed back to the warehouse that day.
     */
    private function ensureLoadsCoverSales(Validator $validator): void
    {
        $date = Carbon::parse($this->input('date'));
        $drivers = User::findMany(array_keys($this->input('loads')))->keyBy('id');
        $returnedQuantities = DriverLoad::whereDate('load_date', $date)->get()
            ->mapWithKeys(fn (DriverLoad $load): array => ["{$load->driver_id}-{$load->product_id}" => $load->returned_quantity]);

        foreach ($this->loads() as $driverId => $products) {
            $soldQuantities = $drivers->get($driverId)?->soldQuantitiesOn($date) ?? collect();

            foreach ($products as $productId => $quantity) {
                $sold = $soldQuantities[$productId] ?? 0;
                $returned = $returnedQuantities->get("{$driverId}-{$productId}", 0);

                if ($quantity < $sold + $returned) {
                    $validator->errors()->add(
                        "loads.{$driverId}.{$productId}",
                        "{$drivers[$driverId]->name} already sold {$sold} and returned {$returned} of this product that day, so the load cannot be lower than ".($sold + $returned).'.',
                    );
                }
            }
        }
    }

    /**
     * Reject loads that need more of a product than the warehouse holds.
     */
    private function ensureEnoughStock(Validator $validator): void
    {
        $existingLoads = DriverLoad::whereDate('load_date', $this->input('date'))->get()
            ->keyBy(fn (DriverLoad $load): string => "{$load->driver_id}-{$load->product_id}");

        $requiredByProduct = [];

        foreach ($this->loads() as $driverId => $products) {
            foreach ($products as $productId => $quantity) {
                $alreadyLoaded = $existingLoads->get("{$driverId}-{$productId}")?->quantity ?? 0;
                $requiredByProduct[$productId] = ($requiredByProduct[$productId] ?? 0) + $quantity - $alreadyLoaded;
            }
        }

        $products = Product::findMany(array_keys($requiredByProduct))->keyBy('id');

        foreach ($requiredByProduct as $productId => $required) {
            $product = $products->get($productId);

            if (! $product) {
                $validator->errors()->add('loads', 'One of the selected products no longer exists.');

                continue;
            }

            if ($required > $product->quantity) {
                $validator->errors()->add(
                    "loads.product.{$productId}",
                    "Not enough {$product->name} in stock: {$required} more needed, only {$product->quantity} available.",
                );
            }
        }
    }
}
