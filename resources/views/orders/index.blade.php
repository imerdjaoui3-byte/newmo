@extends('layouts.app')

@section('title', 'Orders')

@section('content-class', 'page-dashboard')

@section('content')
    <section class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-1">Order List</h5>
                <p class="text-muted small mb-0">Manage incoming delivery orders</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="Filter orders">
                    <a href="{{ route('orders.index') }}"
                        class="btn {{ $showDone ? 'btn-outline-secondary' : 'btn-secondary' }}">Open</a>
                    <a href="{{ route('orders.index', ['status' => 'done']) }}"
                        class="btn {{ $showDone ? 'btn-secondary' : 'btn-outline-secondary' }}">Done</a>
                </div>
                <a href="{{ route('orders.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add
                    Order</a>
            </div>
        </div>
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @error('driver_id')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror
            @error('payment')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror
            <table id="ordersTable" class="table table-stack">
                <thead>
                    <tr>
                        <th>Incoming Number</th>
                        <th>Delivery Address</th>
                        <th>Status</th>
                        <th>Driver</th>
                        <th>Received</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td data-label="Number">
                                <div>
                                    {{ $order->customer->phone }}
                                    <div class="text-muted small" title="Average of the ratings drivers gave this customer"><i class="bi bi-star-fill text-warning"></i> {{ number_format($order->customer->rating, 2) }}</div>
                                </div>
                            </td>
                            <td data-label="Address">{{ $order->address ?? '—' }}</td>
                            <td data-label="Status">
                                <div>
                                    <span
                                        class="la-badge la-badge-{{ $order->statusBadgeVariant() }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                                    @if ($order->payment_method)
                                        <div class="text-muted small">
                                            {{ $order->payment_method === 'emt' ? 'EMT · ' . $order->payment_reference : 'Cash' }}
                                        </div>
                                    @endif
                                    @foreach ($order->items as $item)
                                        <div class="text-muted small">{{ $item->quantity }} × {{ $item->product->name }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td data-label="Driver">
                                <form method="POST" action="{{ route('orders.dispatch', $order) }}" class="d-flex gap-2">
                                    @csrf
                                    <select name="driver_id" class="form-select form-select-sm" required
                                        aria-label="Driver">
                                        <option value="">Select a driver</option>
                                        @foreach ($drivers as $driver)
                                            <option value="{{ $driver->user_id }}" @selected($order->driver_id === $driver->user_id)>
                                                {{ $driver->user->name }}{{ $driver->user->is_online ? '' : ' (offline)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">
                                        {{ $order->driver_id ? 'Reassign' : 'Dispatch' }}
                                    </button>
                                    @if ($order->isOpen())
                                        <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap"
                                            formaction="{{ route('orders.unassign', $order) }}" formnovalidate>
                                            Cancel
                                        </button>
                                    @endif
                                </form>
                            </td>
                            <td data-label="Received">{{ $order->created_at->diffForHumans() }}</td>
                            <td data-label="Actions">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('orders.edit', $order) }}"
                                        class="btn btn-sm btn-icon btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('orders.destroy', $order) }}" data-confirm-form
                                        data-confirm-title="Delete order?"
                                        data-confirm-text="This will permanently remove the order from {{ $order->customer->phone }}."
                                        data-confirm-button="Delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @if ($order->payment_verified_at)
                                        <span class="la-badge la-badge-success align-self-center"
                                            title="{{ $order->payment_received_at?->toDayDateTimeString() }}">Paid{{ $order->payment_received_at ? ' · ' . $order->payment_received_at->diffForHumans() : '' }}</span>
                                    @elseif ($order->awaitsPaymentCheck())
                                        @php($paymentCheck = session('paymentCheck.order_id') === $order->id ? session('paymentCheck') : null)
                                        <form method="POST" action="{{ route('orders.check-payment', $order) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Check</button>
                                        </form>
                                        @if ($order->payment_received_at)
                                            <form method="POST" action="{{ route('orders.mark-paid', $order) }}">
                                                @csrf
                                                <button type="submit"
                                                    class="btn btn-sm btn-outline-success text-nowrap">Mark paid</button>
                                            </form>
                                        @endif
                                        @if ($paymentCheck)
                                            <span
                                                class="small align-self-center text-md-nowrap {{ $paymentCheck['found'] ? 'text-success' : 'text-danger' }}">{{ $paymentCheck['message'] }}</span>
                                        @elseif ($order->payment_received_at)
                                            <span class="small align-self-center text-md-nowrap text-success"
                                                title="{{ $order->payment_received_at->toDayDateTimeString() }}">{{ "Email received {$order->payment_received_at->diffForHumans()}." }}</span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function initOrdersTable(state = {}) {
                const table = new simpleDatatables.DataTable('#ordersTable', {
                    perPage: state.perPage ?? 10,
                    perPageSelect: [5, 10, 25, 50],
                    labels: {
                        placeholder: 'Search orders...',
                    },
                });

                if (state.query) {
                    table.wrapperDOM.querySelector('.datatable-input').value = state.query;
                    table.search(state.query);
                }

                if (state.page > 1) {
                    table.page(state.page);
                }

                return table;
            }

            let ordersTable = initOrdersTable();

            if (!window.Echo) {
                return;
            }

            // Don't swap the table while someone is picking a driver, typing a search or answering a confirmation.
            function isBusy() {
                return ordersTable.wrapperDOM.contains(document.activeElement) || window.Swal?.isVisible();
            }

            let refreshTimer = null;

            function scheduleRefresh(delay = 400) {
                clearTimeout(refreshTimer);
                refreshTimer = setTimeout(refreshOrders, delay);
            }

            async function refreshOrders() {
                if (isBusy()) {
                    scheduleRefresh(2000);
                    return;
                }

                try {
                    const response = await fetch(window.location.href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!response.ok) {
                        return;
                    }

                    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const freshTable = page.getElementById('ordersTable');
                    if (!freshTable) {
                        return;
                    }

                    // Someone started interacting while the page was loading: try again once they're done.
                    if (isBusy()) {
                        scheduleRefresh(2000);
                        return;
                    }

                    const state = {
                        query: ordersTable.wrapperDOM.querySelector('.datatable-input')?.value,
                        page: ordersTable._currentPage,
                        perPage: ordersTable.options.perPage,
                    };

                    ordersTable.destroy();
                    document.getElementById('ordersTable').replaceWith(freshTable);
                    ordersTable = initOrdersTable(state);

                    // Briefly highlight the table so it's visible that the data just changed.
                    ordersTable.wrapperDOM.animate(
                        [{ backgroundColor: 'rgba(250, 204, 21, 0.25)' }, { backgroundColor: 'transparent' }],
                        { duration: 1200, easing: 'ease-out' },
                    );
                } catch (error) {
                    console.error('Live order refresh failed:', error);
                }
            }

            window.Echo.private('orders').listen('OrderChanged', () => scheduleRefresh());
        });
    </script>
@endpush
