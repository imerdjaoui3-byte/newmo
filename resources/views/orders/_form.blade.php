<div class="row g-3">
    <div class="col-md-6">
        <label for="phone" class="form-label">Incoming Number</label>
        <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone"
            value="{{ old('phone', $order?->customer?->phone ?? '') }}" required>
        @error('phone')
            <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
    </div>

    <div class="col-12">
        <label for="address" class="form-label">Delivery Address</label>
        <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="3"
            required>{{ old('address', $order?->address ?? '') }}</textarea>
        @error('address')
            <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
    </div>
</div>
