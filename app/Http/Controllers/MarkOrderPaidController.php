<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkOrderPaidRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class MarkOrderPaidController extends Controller
{
    /**
     * Confirm the order's e-Transfer payment after its email was found in the inbox.
     */
    public function __invoke(MarkOrderPaidRequest $request, Order $order): RedirectResponse
    {
        $order->update(['payment_verified_at' => now()]);

        return back();
    }
}
