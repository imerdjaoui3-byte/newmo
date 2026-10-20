@extends('layouts.app')

@section('title', 'Users')

@section('content-class', 'page-dashboard')

@section('content')
    <section class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-1">User List</h5>
                <p class="text-muted small mb-0">Manage your team's user accounts</p>
            </div>
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add
                User</a>
        </div>
        <div class="card-body">
            <table id="usersTable" class="table table-stack">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Rank</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td data-label="User">
                                <div class="d-flex align-items-center gap-2">
                                    @php($isOnline = $user->isOnline())
                                    <i class="bi bi-circle-fill {{ $isOnline ? 'text-success' : 'text-danger' }}" style="font-size: 0.6rem;"
                                        title="{{ $isOnline ? 'Online' : ($user->last_seen_at ? 'Last seen '.$user->last_seen_at->diffForHumans() : 'Offline') }}"></i>
                                    <span>{{ $user->name }}</span>
                                </div>
                            </td>
                            <td data-label="Email">{{ $user->email }}</td>
                            <td data-label="Rank"><span class="la-badge la-badge-{{ $user->roleBadgeVariant() }}">{{ $user->roleLabel() }}</span></td>
                            <td data-label="Actions">
                                <div class="d-flex gap-2">
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-icon btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('users.destroy', $user) }}"
                                        data-confirm-form data-confirm-title="Delete user?"
                                        data-confirm-text="This will permanently remove {{ $user->name }} and any linked records."
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
            new simpleDatatables.DataTable('#usersTable', {
                perPageSelect: [5, 10, 25, 50],
                labels: {
                    placeholder: 'Search users...',
                },
            });
        });
    </script>
@endpush
