@extends('layouts.app')

@section('title', 'Edit Product')

@section('content-class', 'page-dashboard')

@section('content')
    <section class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-1">Edit Product</h5>
                <p class="text-muted small mb-0">Update this product</p>
            </div>
            <a href="{{ route('products.index') }}" class="btn btn-danger btn-sm"><i class="bi bi-chevron-left me-1"></i>Cancel</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('products.update', $product) }}">
                @csrf
                @method('PUT')

                @include('products._form')

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </section>
@endsection
