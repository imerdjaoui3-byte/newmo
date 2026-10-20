@extends('layouts.app')

@section('title', 'Bill · '.$bill->driver->name.' · '.$bill->bill_date->format('M j, Y'))

@section('content-class', 'page-dashboard')

@section('content')
    <section class="card" id="billCard">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-1">Daily Bill · {{ $bill->driver->name }}</h5>
                <p class="text-muted small mb-0 d-flex align-items-center flex-wrap gap-2">
                    {{ $bill->bill_date->format('l, F j, Y') }}
                    @if ($bill->isClosed())
                        <span class="la-badge la-badge-success">Closed {{ $bill->closed_at->format('g:i a') }}</span>
                    @else
                        <span class="la-badge la-badge-warning" title="Every sale is added as it happens, until the driver returns their stock">Open · live</span>
                    @endif
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('bills.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left me-1"></i>Bills</a>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                @foreach ([
                    ['label' => 'Loaded', 'value' => number_format($bill->loaded_quantity)],
                    ['label' => 'Sold', 'value' => number_format($bill->sold_quantity)],
                    ['label' => 'Returned', 'value' => number_format($bill->returned_quantity)],
                    ['label' => 'Orders Delivered', 'value' => number_format($orders->count())],
                    ['label' => 'Cash', 'value' => Number::money($paymentTotals['cash'] ?? 0)],
                    ['label' => 'EMT', 'value' => Number::money($paymentTotals['emt'] ?? 0)],
                ] as $figure)
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="bill-figure">
                            <div class="text-muted small">{{ $figure['label'] }}</div>
                            <div class="bill-figure-value">{{ $figure['value'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <h6 class="mb-2">Products</h6>
            <table class="table table-stack mb-4">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Loaded</th>
                        <th class="text-end">Sold</th>
                        <th class="text-end">Returned</th>
                        <th class="text-end">Sales</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($productLines as $line)
                        <tr>
                            <td data-label="Product">{{ $line['name'] }}</td>
                            <td data-label="Loaded" class="text-end">{{ $line['loaded'] }}</td>
                            <td data-label="Sold" class="text-end">{{ $line['sold'] }}</td>
                            <td data-label="Returned" class="text-end">{{ $line['returned'] }}</td>
                            <td data-label="Sales" class="text-end">{{ Number::money($line['sales']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted text-center py-3">No products loaded or sold this day.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <h6 class="mb-2">Orders Delivered</h6>
            <table class="table table-stack mb-4">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Customer</th>
                        <th>Address</th>
                        <th>Items</th>
                        <th>Payment</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td data-label="Time">{{ $order->completed_at->format('g:i a') }}</td>
                            <td data-label="Customer">{{ $order->customer->phone }}</td>
                            <td data-label="Address">{{ $order->address ?? '—' }}</td>
                            <td data-label="Items">
                                <div>
                                    @foreach ($order->items as $item)
                                        <div class="small">{{ $item->quantity }} × {{ $item->product->name }} @ {{ Number::money($item->unit_price) }}</div>
                                    @endforeach
                                </div>
                            </td>
                            <td data-label="Payment">
                                <div>
                                    @if ($order->payment_method === 'emt')
                                        EMT · {{ $order->payment_reference }}
                                        <div class="small {{ $order->payment_verified_at ? 'text-success' : 'text-warning' }}">{{ $order->payment_verified_at ? 'Verified' : 'Not verified yet' }}</div>
                                    @elseif ($order->payment_method === 'cash')
                                        Cash
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Total" class="text-end">{{ Number::money($order->total()) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted text-center py-3">No orders delivered this day.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="bill-total">
                <span>Total</span>
                <strong>{{ Number::money($bill->total_amount) }}</strong>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.Echo) {
                return;
            }

            // Every sale updates the bill: reload it in place.
            async function refreshBill() {
                try {
                    const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const freshBill = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById('billCard');
                    if (freshBill) {
                        document.getElementById('billCard').replaceWith(freshBill);
                    }
                } catch (error) {
                    // keep the current bill until the next change
                }
            }

            window.Echo.private(@json(auth()->user()->isDriver() ? 'App.Models.User.'.auth()->id() : 'bills'))
                .listen('BillUpdated', (event) => {
                    if (event.bill.id === {{ $bill->id }}) {
                        refreshBill();
                    }
                });
        });
    </script>
@endpush

@push('styles')
    <style>
        .bill-figure { padding: .75rem 1rem; border: 1px solid var(--border-color); border-radius: .5rem; }
        .bill-figure-value { color: var(--heading-color); font-size: 1.25rem; font-weight: 700; }
        .bill-total {
            display: flex;
            justify-content: flex-end;
            align-items: baseline;
            gap: 1.5rem;
            padding-top: 1rem;
            border-top: 2px solid var(--border-color);
            font-size: 1.1rem;
        }
        .bill-total strong { color: var(--heading-color); font-size: 1.5rem; }
    </style>
@endpush
