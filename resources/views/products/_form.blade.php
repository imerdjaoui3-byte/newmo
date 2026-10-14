<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Name</label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
            value="{{ old('name', $product?->name ?? '') }}" required>
        @error('name')
            <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="price" class="form-label">Price</label>
        <input type="number" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror" id="price"
            name="price" value="{{ old('price', $product?->price ?? '') }}" required>
        @error('price')
            <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="quantity" class="form-label">Quantity in stock</label>
        <input type="number" step="1" min="0" class="form-control @error('quantity') is-invalid @enderror" id="quantity"
            name="quantity" value="{{ old('quantity', $product?->quantity ?? 0) }}" required>
        @error('quantity')
            <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
    </div>
</div>
