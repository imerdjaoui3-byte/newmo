<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Name</label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
            value="{{ old('name', $user?->name ?? '') }}" required>
        @error('name')
            <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
            value="{{ old('email', $user?->email ?? '') }}" required>
        @error('email')
            <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="password" class="form-label">Password{{ $user ? ' (leave blank to keep current)' : '' }}</label>
        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
            name="password" @if (! $user) required @endif>
        @error('password')
            <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">Confirm Password</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
            @if (! $user) required @endif>
    </div>

    <div class="col-md-6">
        <label for="rank" class="form-label">Rank</label>
        <select class="form-select @error('rank') is-invalid @enderror" id="rank" name="rank" required>
            @if ($user?->isPending())
                <option value="" selected disabled>Pending approval — choose a rank</option>
            @endif
            @foreach (\App\Models\User::roleLabels() as $rank => $label)
                @continue($rank === 0)
                <option value="{{ $rank }}" @selected(old('rank', $user ? $user->rank : 3) == $rank)>{{ $rank }} - {{ $label }}</option>
            @endforeach
        </select>
        @error('rank')
            <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
    </div>

    @php $driver = $user?->driver; @endphp

    <div class="col-12" id="driver-fields" hidden>
        <hr class="my-2">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="phone" class="form-label">Phone</label>
                <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone"
                    name="phone" value="{{ old('phone', $driver?->phone ?? '') }}">
                @error('phone')
                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="car" class="form-label">Car</label>
                <input type="text" class="form-control @error('car') is-invalid @enderror" id="car" name="car"
                    value="{{ old('car', $driver?->car ?? '') }}">
                @error('car')
                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="color" class="form-label">Color</label>
                <input type="text" class="form-control @error('color') is-invalid @enderror" id="color"
                    name="color" value="{{ old('color', $driver?->color ?? '') }}">
                @error('color')
                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rankSelect = document.getElementById('rank');
            const driverFields = document.getElementById('driver-fields');

            function toggleDriverFields() {
                driverFields.hidden = rankSelect.value !== '3';
            }

            rankSelect.addEventListener('change', toggleDriverFields);
            toggleDriverFields();
        });
    </script>
</div>
