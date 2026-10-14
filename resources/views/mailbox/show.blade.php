@extends('layouts.app')

@section('title', 'Mailbox')

@section('content-class', 'page-dashboard')

@section('content')
    <section class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-1">My Inbox</h5>
                <p class="text-muted small mb-0">Read-only access to your Gmail</p>
            </div>
            @if ($mailbox)
                <form method="POST" action="{{ route('mailbox.destroy') }}"
                    data-confirm-form data-confirm-title="Disconnect inbox?"
                    data-confirm-text="The app will lose access to {{ $mailbox->email }} until it is connected again."
                    data-confirm-button="Disconnect">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg me-1"></i>Disconnect</button>
                </form>
            @endif
        </div>
        <div class="card-body">
            @error('mailbox')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            @if ($mailbox)
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="la-badge la-badge-success"><i class="bi bi-check-circle me-1"></i>Connected</span>
                    <span class="fw-semibold">{{ $mailbox->email }}</span>
                    <span class="text-muted small">since {{ $mailbox->created_at->diffForHumans() }}</span>
                </div>

                @if ($error)
                    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <span>{{ $error }}</span>
                        <a href="{{ route('mailbox.connect') }}" class="btn btn-sm btn-warning">Reconnect</a>
                    </div>
                @else
                    <h6 class="mb-2">Latest emails</h6>
                    <ul class="list-group list-group-flush">
                        @forelse ($messages as $message)
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between gap-2">
                                    <span class="fw-semibold text-truncate">{{ $message['subject'] ?? '(no subject)' }}</span>
                                    <span class="text-muted small text-nowrap">{{ $message['date']?->diffForHumans() }}</span>
                                </div>
                                <div class="text-muted small text-truncate">{{ $message['from'] }}</div>
                                <div class="small text-truncate">{{ $message['snippet'] }}</div>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-muted">The inbox is empty.</li>
                        @endforelse
                    </ul>
                @endif
            @else
                <div class="text-center py-4">
                    <p class="mb-1 fw-semibold">No inbox connected</p>
                    <p class="text-muted small mb-3">Sign in with your Google account to let the app read your emails. It only asks for read access.</p>
                    <a href="{{ route('mailbox.connect') }}" class="btn btn-primary"><i class="bi bi-google me-1"></i>Connect Gmail inbox</a>
                </div>
            @endif
        </div>
    </section>
@endsection
