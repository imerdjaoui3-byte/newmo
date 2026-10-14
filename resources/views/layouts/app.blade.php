<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard')</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="LiteAdmin - Bootstrap Admin Template">
    <meta name="keywords" content="admin, dashboard, bootstrap">

    <!-- Favicons -->
    <link href="{{ asset('assets/img/favicon.png') }}" rel="icon">

    <!-- Google Fonts - Inter + JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="{{ asset('assets/vendors/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendors/remixicon/remixicon.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendors/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendors/phosphor-icons/phosphor-icons.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendors/lucide-icons/lucide.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendors/simple-datatables/style.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendors/quill/quill.snow.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendors/quill/quill.bubble.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendors/choices.js/choices.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendors/flatpickr/flatpickr.min.css') }}" rel="stylesheet">

    <!-- Template Main CSS File -->
    <link href="{{ asset('assets/css/main.css') }}" rel="stylesheet">

    <!-- Keep topbar/sidebar dark regardless of the light/dark toggle -->
    <link href="{{ asset('assets/css/theme-overrides.css') }}" rel="stylesheet">

    <!-- App-specific additions (avatars, badges, icon buttons) -->
    <link href="{{ asset('assets/css/custom.css') }}?v={{ filemtime(public_path('assets/css/custom.css')) }}" rel="stylesheet">

    @stack('styles')

    <!-- =======================================================
    * Template Name: LiteAdmin - Bootstrap Admin Template
    * Template URL: https://bootstrapmade.com/lite-admin-dashboard-template/
    * Updated: Apr 8, 2026 with Bootstrap v5.3.8
    * Author: BootstrapMade.com
    * License: https://bootstrapmade.com/license/
    ======================================================== -->
</head>

<body>
    @include('partials.topbar')

    @include('partials.sidebar')

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay"></div>

    <!-- Main Content -->
    <main class="main">
        <div class="main-content @yield('content-class')">
            @yield('content')
        </div>

        <!-- Footer -->

    </main>

    @if (session('promptNotifications') || auth()->user()->isDriver())
        <!-- Shown right after login, and to drivers until they answer (they need dispatch alerts); the browser only allows the permission prompt from a click -->
        <div class="card shadow position-fixed bottom-0 end-0 m-3 d-none" id="notificationPrompt"
            style="z-index: 1080; max-width: 22rem;">
            <div class="card-body d-flex gap-3">
                <i class="bi bi-bell fs-4 text-primary"></i>
                <div>
                    <p class="fw-semibold mb-1">Turn on notifications?</p>
                    <p class="text-muted small mb-2">Get alerted about new orders and updates, even when this tab is closed.</p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary btn-sm" data-enable-notifications>Enable</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss-notifications>Not now</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Back to Top -->
    <a href="#" class="back-to-top">
        <i class="bi bi-arrow-up"></i>
    </a>

    <!-- Vendor JS Files -->
    <script src="{{ asset('assets/vendors/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/apexcharts/apexcharts.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/chart.js/chart.umd.js') }}"></script>
    <script src="{{ asset('assets/vendors/echarts/echarts.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/simple-datatables/simple-datatables.js') }}"></script>
    <script src="{{ asset('assets/vendors/quill/quill.js') }}"></script>
    <script src="{{ asset('assets/vendors/tinymce/tinymce.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/choices.js/choices.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/php-email-form/validate.js') }}"></script>

    <!-- Template Main JS Files -->
    <script src="{{ asset('assets/js/theme.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>

    <!-- App Sidebar Toggle (for app pages with sidebars) -->
    <script src="{{ asset('assets/js/apps-sidebar-toggle.js') }}"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script type="module" src="{{ asset('js/push-notifications.js') }}"
        data-subscription-url="{{ route('push-subscriptions.store') }}"
        data-service-worker-url="{{ asset('service-worker.js') }}"
        data-vapid-public-key="{{ config('webpush.vapid.public_key') }}"></script>
    @vite(['resources/js/app.js'])

    @stack('scripts')
</body>

</html>
