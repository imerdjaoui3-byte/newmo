@extends('layouts.app')

@section('title', 'Bills')

@section('content-class', 'page-dashboard')

@section('content')
    <section class="card">
        <div class="card-header">
            <h5 class="card-title mb-1">Bills</h5>
            <p class="text-muted small mb-0">Each driver's daily sales, issued when they return their stock</p>
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
                            <td data-label="Total">{{ number_format($bill->total_amount, 2) }}</td>
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
            new simpleDatatables.DataTable('#billsTable', {
                perPageSelect: [5, 10, 25, 50],
                labels: {
                    placeholder: 'Search bills...',
                },
            });
        });
    </script>
@endpush
