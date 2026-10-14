<!-- Sidebar -->
<aside class="sidebar">
    <!-- Sidebar Navigation -->
    <nav class="sidebar-nav">
        <button class="sidebar-close" aria-label="Close sidebar">
            <i class="bi bi-x-lg"></i>
        </button>

        <ul class="nav-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <span class="nav-icon"><i class="ph ph-squares-four"></i></span>
                    <span class="nav-text">Dashboard</span>

                </a>
            </li>
            @unless (auth()->user()->isDriver())
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                        href="{{ route('users.index') }}">
                        <span class="nav-icon"><i class="ph ph-users-three"></i></span>
                        <span class="nav-text">Users</span>

                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}"
                        href="{{ route('products.index') }}">
                        <span class="nav-icon"><i class="ph ph-package"></i></span>
                        <span class="nav-text">Products</span>

                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}"
                        href="{{ route('orders.index') }}">
                        <span class="nav-icon"><i class="ph ph-receipt"></i></span>
                        <span class="nav-text">Orders</span>

                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('driver-loads.*') ? 'active' : '' }}"
                        href="{{ route('driver-loads.index') }}">
                        <span class="nav-icon"><i class="ph ph-truck"></i></span>
                        <span class="nav-text">Driver Stock</span>

                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('drivers.tracking') ? 'active' : '' }}"
                        href="{{ route('drivers.tracking') }}">
                        <span class="nav-icon"><i class="ph ph-map-pin"></i></span>
                        <span class="nav-text">Live Tracking</span>

                    </a>
                </li>
            @endunless
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('bills.*') ? 'active' : '' }}"
                        href="{{ route('bills.index') }}">
                        <span class="nav-icon"><i class="ph ph-invoice"></i></span>
                        <span class="nav-text">Bills</span>

                    </a>
                </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-footer-user">
            <a href="#" class="sidebar-footer-profile">
                <img src="{{ asset('assets/img/profile-img.webp') }}" alt="User" class="sidebar-footer-avatar">
                <div class="sidebar-footer-info">
                    <div class="sidebar-footer-name">{{ auth()->user()->name }}</div>
                    <div class="sidebar-footer-role">{{ auth()->user()->roleLabel() }}</div>
                </div>
            </a>
            <div class="sidebar-footer-actions">
                <a href="#" class="sidebar-footer-action" title="Settings">
                    <i class="bi bi-gear"></i>
                </a>
                <form method="POST" action="{{ route('logout') }}" data-logout-form>
                    @csrf
                    <button type="submit"
                        class="sidebar-footer-action sidebar-footer-logout border-0 bg-transparent p-0" title="Logout">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>
