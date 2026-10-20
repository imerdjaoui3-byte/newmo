@extends('layouts.app')

@section('title', 'Dashboard')

@section('content-class', 'page-dashboard')

@section('content')
    <div class="la-dashboard-v2">
        {{-- Figures that change with every order: reloaded live when an order changes. --}}
        <div id="dashboardLive">
            <div class="row g-3 mb-3">
                @foreach ([
                    ['icon' => 'bi-box-seam', 'value' => number_format($stats['received_today']), 'label' => 'Orders Today', 'change' => $stats['received_change']],
                    ['icon' => 'bi-hourglass-split', 'value' => number_format($stats['awaiting_driver']), 'label' => 'Awaiting Driver', 'change' => null],
                    ['icon' => 'bi-truck', 'value' => number_format($stats['in_transit']), 'label' => 'Orders in Transit', 'change' => null],
                    ['icon' => 'bi-check2-circle', 'value' => number_format($stats['delivered_today']), 'label' => 'Delivered Today', 'change' => $stats['delivered_change']],
                    ['icon' => 'bi-cash-stack', 'value' => Number::money($stats['revenue_today']), 'label' => 'Revenue Today', 'change' => $stats['revenue_change']],
                    ['icon' => 'bi-envelope-check', 'value' => number_format($stats['emt_to_check']), 'label' => 'EMT Awaiting Check', 'change' => null, 'url' => route('orders.index', ['status' => 'done'])],
                ] as $stat)
                    <div class="col-sm-6 col-xl-4">
                        <div class="card stat-card h-100 {{ isset($stat['url']) ? 'position-relative' : '' }}">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-card-icon"><i class="bi {{ $stat['icon'] }}"></i></div>
                                <div>
                                    <div class="stat-card-value">{{ $stat['value'] }}</div>
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        @isset($stat['url'])
                                            <a href="{{ $stat['url'] }}" class="stat-card-label stretched-link">{{ $stat['label'] }}</a>
                                        @else
                                            <span class="stat-card-label">{{ $stat['label'] }}</span>
                                        @endisset
                                        @if ($stat['change'] !== null)
                                            <span class="stat-card-change {{ $stat['change'] < 0 ? 'is-down' : '' }}" title="Compared with yesterday">{{ $stat['change'] >= 0 ? '+' : '' }}{{ $stat['change'] }}%</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <section class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-1">Top Drivers</h5>
                            <p class="text-muted small mb-0">Most deliveries in the last 7 days</p>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-stack mb-0">
                                <thead>
                                    <tr>
                                        <th>Driver</th>
                                        <th class="text-end">Deliveries</th>
                                        <th class="text-end">Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($topDrivers as $driver)
                                        <tr>
                                            <td data-label="Driver">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="leaderboard-rank">{{ $loop->iteration }}</span>
                                                    <span>{{ $driver['name'] }}</span>
                                                </div>
                                            </td>
                                            <td data-label="Deliveries" class="text-end">{{ number_format($driver['deliveries']) }}</td>
                                            <td data-label="Sales" class="text-end">{{ Number::money($driver['sales']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-muted text-center py-3">No deliveries in the last 7 days.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
                <div class="col-lg-6">
                    <section class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-1">Best-Selling Products</h5>
                            <p class="text-muted small mb-0">Units sold on deliveries in the last 7 days</p>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-stack mb-0">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Units</th>
                                        <th class="text-end">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($bestSellers as $product)
                                        <tr>
                                            <td data-label="Product">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="leaderboard-rank">{{ $loop->iteration }}</span>
                                                    <span>{{ $product['name'] }}</span>
                                                </div>
                                            </td>
                                            <td data-label="Units" class="text-end">{{ number_format($product['units']) }}</td>
                                            <td data-label="Revenue" class="text-end">{{ Number::money($product['revenue']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-muted text-center py-3">No products sold in the last 7 days.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <section class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Orders per Day</h5>
                        <p class="text-muted small mb-0">Orders received over the last 14 days</p>
                    </div>
                    <div class="card-body">
                        <div id="ordersPerDayChart" role="img" aria-label="Orders received per day over the last 14 days"></div>
                    </div>
                </section>
            </div>
            <div class="col-lg-5">
                <section class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Busiest Hours</h5>
                        <p class="text-muted small mb-0">Orders received by hour of day, last 30 days</p>
                    </div>
                    <div class="card-body">
                        <div id="ordersPerHourChart" role="img" aria-label="Orders received by hour of day over the last 30 days"></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .stat-card-icon {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: .75rem;
            background-color: color-mix(in srgb, var(--muted-color) 10%, transparent);
            color: var(--heading-color);
            font-size: 1.35rem;
        }
        .stat-card-value { color: var(--heading-color); font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
        .stat-card-label { color: var(--muted-color); font-size: .875rem; text-decoration: none; }
        a.stat-card-label:hover { color: var(--accent-color); }
        .stat-card-change {
            padding: .1rem .55rem;
            border-radius: 999px;
            background-color: color-mix(in srgb, var(--success-color) 15%, transparent);
            color: var(--success-color);
            font-size: .75rem;
            font-weight: 600;
        }
        .stat-card-change.is-down {
            background-color: color-mix(in srgb, var(--danger-color) 15%, transparent);
            color: var(--danger-color);
        }
        .leaderboard-rank {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background-color: color-mix(in srgb, var(--accent-color) 12%, transparent);
            color: var(--accent-color);
            font-size: .75rem;
            font-weight: 600;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function() {
            'use strict';

            // Reload the figures from the server when any order changes.
            let refreshTimer = null;

            async function refreshFigures() {
                try {
                    const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const freshFigures = page.getElementById('dashboardLive');
                    if (freshFigures) {
                        document.getElementById('dashboardLive').replaceWith(freshFigures);
                    }
                } catch (error) {
                    // keep the current figures until the next change
                }
            }

            // Echo is set up by the app bundle, which only runs once the page has loaded.
            document.addEventListener('DOMContentLoaded', () => {
                window.Echo?.private('orders').listen('OrderChanged', () => {
                    clearTimeout(refreshTimer);
                    refreshTimer = setTimeout(refreshFigures, 500);
                });
            });
        })();

        (function() {
            'use strict';
            if (typeof ApexCharts === 'undefined') return;

            const ordersPerDay = @json($ordersPerDay);
            const ordersPerHour = @json($ordersPerHour);
            const charts = [];

            function cssVar(name) {
                return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            }

            function hourLabel(hour) {
                const suffix = hour < 12 ? 'am' : 'pm';
                return `${hour % 12 || 12}${suffix}`;
            }

            function barChart(selector, categories, data, labelEvery) {
                const muted = cssVar('--muted-color');
                const chart = new ApexCharts(document.querySelector(selector), {
                    chart: { type: 'bar', height: 260, toolbar: { show: false }, animations: { enabled: false }, fontFamily: 'inherit' },
                    series: [{ name: 'Orders', data }],
                    colors: [cssVar('--accent-color')],
                    plotOptions: { bar: { columnWidth: '60%', borderRadius: 4, borderRadiusApplication: 'end' } },
                    dataLabels: { enabled: false },
                    xaxis: {
                        categories,
                        labels: {
                            rotate: 0,
                            hideOverlappingLabels: true,
                            style: { colors: muted, fontSize: '12px' },
                            formatter: (value, timestamp, opts) => (opts?.i ?? categories.indexOf(value)) % labelEvery === 0 ? value : '',
                        },
                        tooltip: { enabled: false },
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                    },
                    yaxis: { labels: { style: { colors: muted, fontSize: '12px' }, formatter: (value) => Math.round(value) } },
                    grid: { borderColor: cssVar('--border-color'), strokeDashArray: 4 },
                    tooltip: { theme: document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light', x: { formatter: (value, { dataPointIndex }) => categories[dataPointIndex] } },
                    states: { hover: { filter: { type: 'darken', value: 0.85 } } },
                });
                chart.render();
                charts.push(chart);
            }

            function renderAll() {
                barChart('#ordersPerDayChart', Object.keys(ordersPerDay), Object.values(ordersPerDay), 2);
                barChart('#ordersPerHourChart', Object.keys(ordersPerHour).map(Number).map(hourLabel), Object.values(ordersPerHour), 3);
            }

            function rerender() {
                while (charts.length) {
                    charts.pop().destroy();
                }
                renderAll();
            }

            renderAll();
            document.addEventListener('themeChanged', rerender);
            new MutationObserver((mutations) => {
                if (mutations.some((mutation) => mutation.attributeName === 'data-theme')) {
                    rerender();
                }
            }).observe(document.documentElement, { attributes: true });
        })();
    </script>
@endpush
