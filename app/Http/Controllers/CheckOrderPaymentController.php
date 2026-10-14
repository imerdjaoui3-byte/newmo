<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckOrderPaymentRequest;
use App\Models\Order;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;

class CheckOrderPaymentController extends Controller
{
    /**
     * Search the user's inbox for an e-Transfer email quoting the order's payment reference, leaving the order unpaid until confirmed.
     */
    public function __invoke(CheckOrderPaymentRequest $request, Order $order): RedirectResponse
    {
        try {
            $receivedAt = $request->user()->mailbox->findMessageDateContaining($order->payment_reference);
        } catch (RequestException $exception) {
            report($exception);

            return $this->backWithResult($order, false, 'Could not read the inbox — sign out and sign back in with Google.');
        }

        $order->update(['payment_received_at' => $receivedAt]);

        if (! $receivedAt) {
            return $this->backWithResult($order, false, "No email found with {$order->payment_reference}.");
        }

        return $this->backWithResult($order, true, "Email received {$receivedAt->diffForHumans()}.");
    }

    /**
     * Redirect back with the check's outcome, shown on the order's own row.
     */
    private function backWithResult(Order $order, bool $isFound, string $message): RedirectResponse
    {
        return back()->with('paymentCheck', ['order_id' => $order->id, 'found' => $isFound, 'message' => $message]);
    }
}
