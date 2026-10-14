<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BillController extends Controller
{
    /**
     * List the drivers' end-of-day bills; drivers only see their own.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $bills = Bill::query()
            ->when($user->isDriver(), fn ($query) => $query->whereBelongsTo($user, 'driver'))
            ->with('driver')
            ->latest('bill_date')
            ->get();

        return view('bills.index', compact('bills'));
    }
}
