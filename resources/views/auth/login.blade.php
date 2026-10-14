@extends('layouts.guest')

@section('title', 'Login')

@section('content')
    <div class="fauth fauth-centered">
        <main class="fauth-main">
            <div class="fauth-main-inner">


                <div class="fauth-stage">
                    <span class="fauth-stage-text">Secure sign in to your workspace</span>
                    <span class="fauth-stage-chip">Login</span>
                </div>

                <div class="fauth-card">
                    @if (session('status'))
                        <div class="alert alert-info small">{{ session('status') }}</div>
                    @endif

                    <form class="fauth-form" method="POST" action="{{ route('login') }}" data-login-form novalidate>
                        @csrf

                        <div class="fauth-field">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                name="email" value="{{ old('email') }}" placeholder="name@example.com" required
                                autofocus>
                            @error('email')
                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="fauth-field">
                            <div class="fauth-row-between">
                                <label for="password" class="form-label">Password</label>
                                <a href="#" class="fauth-link">Forgot password?</a>
                            </div>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                id="password" name="password" placeholder="Enter your password" required>
                            @error('password')
                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="fauth-row-between mb-2">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Sign In</button>
                    </form>

                    <div class="d-flex align-items-center gap-2 my-3 text-muted small">
                        <hr class="flex-grow-1 m-0"> or <hr class="flex-grow-1 m-0">
                    </div>

                    <a href="{{ route('google.redirect') }}" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-google"></i>Sign in with Google
                    </a>
                </div>


            </div>
        </main>
    </div>
@endsection
