@extends('layouts.app')

@section('title', 'Driver Stock')

@section('content-class', 'page-dashboard')

@section('content')
    <section class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-1">Driver Stock</h5>
                <p class="text-muted small mb-0">Quantity each driver starts the day with</p>
            </div>
            <form method="GET" action="{{ route('driver-loads.index') }}" class="d-flex gap-2">
                <input type="date" name="date" class="form-control form-control-sm" value="{{ $date->toDateString() }}"
                    aria-label="Day" onchange="this.form.submit()">
            </form>
        </div>
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @endif

            @if ($drivers->isEmpty() || $products->isEmpty())
                <p class="text-muted text-center py-4 mb-0">
                    You need at least one active driver and one product to load stock.
                </p>
            @else
                <form method="POST" action="{{ route('driver-loads.update') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">

                    <div class="table-responsive">
                        <table class="table table-stack align-middle">
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    @foreach ($products as $product)
                                        <th class="text-nowrap">{{ $product->name }}</th>
                                    @endforeach
                                    <th class="text-nowrap">End of Day</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($drivers as $driver)
                                    @php($isReturned = $returnedDriverIds->contains($driver->user_id))
                                    <tr>
                                        <td class="text-nowrap" data-label="Driver">{{ $driver->user->name }}</td>
                                        @foreach ($products as $product)
                                            @php($field = "loads.{$driver->user_id}.{$product->id}")
                                            <td data-label="{{ $product->name }}">
                                                <input type="number" step="1" min="0"
                                                    name="loads[{{ $driver->user_id }}][{{ $product->id }}]"
                                                    value="{{ old($field, $loads->get("{$driver->user_id}-{$product->id}", 0)) }}"
                                                    class="form-control form-control-sm @error($field) is-invalid @enderror"
                                                    style="min-width: 5rem"
                                                    aria-label="{{ $product->name }} for {{ $driver->user->name }}">
                                            </td>
                                        @endforeach
                                        <td data-label="End of Day">
                                            @if ($isReturned)
                                                <span class="la-badge la-badge-success">Returned</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="text-muted small">
                                    <td class="fw-semibold">Left in stock</td>
                                    @foreach ($products as $product)
                                        <td data-label="{{ $product->name }}">
                                            <span class="la-badge la-badge-{{ $product->stockBadgeVariant() }}">{{ $product->quantity }}</span>
                                        </td>
                                    @endforeach
                                    <td class="d-none d-md-table-cell"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Save Driver Stock</button>
                    </div>
                </form>

            @endif
        </div>
    </section>
@endsection
