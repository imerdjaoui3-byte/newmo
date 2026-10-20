@extends('layouts.app')

@section('title', 'Products')

@section('content-class', 'page-dashboard')

@section('content')
    <section class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-1">Product List</h5>
                <p class="text-muted small mb-0">Manage your products</p>
            </div>
            <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add
                Product</a>
        </div>
        <div class="card-body">
            @error('adjustment')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror
            <table id="productsTable" class="table table-stack">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Price</th>
                        <th>In Stock</th>
                        <th>Adjust Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td data-label="Name">{{ $product->name }}</td>
                            <td data-label="Price">{{ Number::money($product->price) }}</td>
                            <td data-label="In Stock"><span class="la-badge la-badge-{{ $product->stockBadgeVariant() }}">{{ $product->quantity }}</span></td>
                            <td data-label="Adjust">
                                <form method="POST" action="{{ route('products.stock', $product) }}" class="d-flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="number" step="1" name="adjustment" class="form-control form-control-sm"
                                        style="max-width: 7rem" placeholder="+10 / -5" required aria-label="Stock adjustment">
                                    <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">Apply</button>
                                </form>
                            </td>
                            <td data-label="Actions">
                                <div class="d-flex gap-2">
                                    <a href="{{ route('products.edit', $product) }}"
                                        class="btn btn-sm btn-icon btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('products.destroy', $product) }}"
                                        data-confirm-form data-confirm-title="Delete product?"
                                        data-confirm-text="This will permanently remove {{ $product->name }}."
                                        data-confirm-button="Delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
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
            new simpleDatatables.DataTable('#productsTable', {
                perPageSelect: [5, 10, 25, 50],
                labels: {
                    placeholder: 'Search products...',
                },
            });
        });
    </script>
@endpush
