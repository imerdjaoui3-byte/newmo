@extends('layouts.app')

@section('title', 'Bills')

@section('content-class', 'page-dashboard')

@section('content')
    <section class="card">
        <div class="card-header">
            <h5 class="card-title mb-1">Bills</h5>
            <p class="text-muted small mb-0">Each driver's daily sales, added live as they sell and closed when they return their stock</p>
        </div>
        <div class="card-body">
            <table id="billsTable" class="table table-stack">
                <thead>
                    <tr>
                        <th>Date</th>
                        @unless (auth()->user()->isDriver())
                            <th>Driver</th>
                        @endunless
                        <th>Loaded</th>
                        <th>Sold</th>
                        <th>Returned</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bills as $bill)
                        <tr>
                            <td data-label="Date">{{ $bill->bill_date->format('M j, Y') }}</td>
                            @unless (auth()->user()->isDriver())
                                <td data-label="Driver">{{ $bill->driver->name }}</td>
                            @endunless
                            <td data-label="Loaded">{{ $bill->loaded_quantity }}</td>
                            <td data-label="Sold">{{ $bill->sold_quantity }}</td>
                            <td data-label="Returned">{{ $bill->returned_quantity }}</td>
                            <td data-label="Total">{{ Number::money($bill->total_amount) }}</td>
                            <td data-label="Status">
                                @if ($bill->isClosed())
                                    <span class="la-badge la-badge-success">Closed</span>
                                @else
                                    <span class="la-badge la-badge-warning">Open · live</span>
                                @endif
                            </td>
                            <td data-label="Details">
                                <a href="{{ route('bills.show', $bill) }}" class="btn btn-sm btn-outline-primary text-nowrap">
                                    <i class="bi bi-receipt me-1"></i>View bill
                                </a>
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
            function initBillsTable(state = {}) {
                const table = new simpleDatatables.DataTable('#billsTable', {
                    perPage: state.perPage ?? 10,
                    perPageSelect: [5, 10, 25, 50],
                    labels: {
                        placeholder: 'Search bills...',
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

            let billsTable = initBillsTable();

            if (!window.Echo) {
                return;
            }

            // Every sale updates a bill: reload the list in place, keeping the search and page.
            let refreshTimer = null;

            async function refreshBills() {
                try {
                    const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const freshTable = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById('billsTable');
                    if (!freshTable) {
                        return;
                    }

                    const state = {
                        query: billsTable.wrapperDOM.querySelector('.datatable-input')?.value,
                        page: billsTable._currentPage,
                        perPage: billsTable.options.perPage,
                    };

                    billsTable.destroy();
                    document.getElementById('billsTable').replaceWith(freshTable);
                    billsTable = initBillsTable(state);
                } catch (error) {
                    // keep the current list until the next change
                }
            }

            window.Echo.private(@json(auth()->user()->isDriver() ? 'App.Models.User.'.auth()->id() : 'bills'))
                .listen('BillUpdated', () => {
                    clearTimeout(refreshTimer);
                    refreshTimer = setTimeout(refreshBills, 500);
                });
        });
    </script>
@endpush
