<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustProductStockRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;

class ProductStockController extends Controller
{
    /**
     * Add stock to (or remove stock from) the product's warehouse quantity.
     */
    public function __invoke(AdjustProductStockRequest $request, Product $product): RedirectResponse
    {
        $product->increment('quantity', $request->validated('adjustment'));

        return redirect()->route('products.index')->with('status', "Stock for {$product->name} updated.");
    }
}
