@extends('layouts.app')

@section('title', 'Add Order')

@section('content-class', 'page-dashboard')

@section('content')
    <section class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-1">Add Order</h5>
                <p class="text-muted small mb-0">Create a new delivery order</p>
            </div>
            <a href="{{ route('orders.index') }}" class="btn btn-danger btn-sm"><i class="bi bi-chevron-left me-1"></i>Cancel</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('orders.store') }}">
                @csrf

                @include('orders._form', ['order' => null])

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Create Order</button>
                </div>
            </form>
        </div>
    </section>
@endsection
