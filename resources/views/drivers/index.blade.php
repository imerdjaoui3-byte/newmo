@extends('layouts.app')

@section('title', 'Drivers')

@section('content-class', 'page-dashboard')

@section('content')
    <section class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-1">Driver List</h5>
                <p class="text-muted small mb-0">Manage your fleet's driver profiles</p>
            </div>
            <a href="{{ route('drivers.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add
                Driver</a>
        </div>
        <div class="card-body">
            <table id="driversTable" class="table table-stack">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Phone</th>
                        <th>Car</th>
                        <th>Color</th>
                        <th>Status</th>
                        <th>Rating</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($drivers as $driver)
                        <tr>
                            <td data-label="Driver">
                                @if ($driver->user)
                                    <div class="d-flex align-items-center gap-2">
                                        @php($isOnline = $driver->isOnline())
                                        <i class="bi bi-circle-fill {{ $isOnline ? 'text-success' : 'text-danger' }}" style="font-size: 0.6rem;"
                                            title="{{ $isOnline ? 'Online' : ($driver->user->last_seen_at ? 'Last seen '.$driver->user->last_seen_at->diffForHumans() : 'Offline') }}"></i>
                                        <span>{{ $driver->user->name }}</span>
                                    </div>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td data-label="Phone">{{ $driver->phone }}</td>
                            <td data-label="Car">{{ $driver->car ?? '—' }}</td>
                            <td data-label="Color">{{ $driver->color ?? '—' }}</td>
                            <td data-label="Status"><span class="la-badge la-badge-{{ $driver->statusBadgeVariant() }}">{{ ucfirst(str_replace('_', ' ', $driver->status)) }}</span></td>
                            <td data-label="Rating">{{ $driver->rating }}</td>
                            <td data-label="Active">
                                @if ($driver->is_active)
                                    <span class="la-badge la-badge-success">Yes</span>
                                @else
                                    <span class="la-badge la-badge-danger">No</span>
                                @endif
                            </td>
                            <td data-label="Actions">
                                <div class="d-flex gap-2">
                                    <a href="{{ route('drivers.edit', $driver) }}" class="btn btn-sm btn-icon btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('drivers.destroy', $driver) }}"
                                        data-confirm-form data-confirm-title="Delete driver?"
                                        data-confirm-text="This will permanently remove {{ $driver->user?->name ?? 'this driver' }} from your fleet."
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
            new simpleDatatables.DataTable('#driversTable', {
                perPageSelect: [5, 10, 25, 50],
                labels: {
                    placeholder: 'Search drivers...',
                },
            });
        });
    </script>
@endpush
