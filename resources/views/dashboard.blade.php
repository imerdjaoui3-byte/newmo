@extends('layouts.app')

@section('title', 'Dashboard')

@section('content-class', 'page-dashboard')

@section('content')
    <div class="la-dashboard-v2">
        <section class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-1">Drivers Online</h5>
                    <p class="text-muted small mb-0">Currently logged in with a known location</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="la-badge la-badge-info" id="onlineDriversCount">{{ $onlineDrivers->count() }} online</span>
                    <a href="{{ route('drivers.tracking') }}" class="btn btn-sm btn-outline-secondary">View map</a>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-stack mb-0" id="onlineDriversTable">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Car</th>
                            <th>Location</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($onlineDrivers as $driver)
                            <tr>
                                <td data-label="Driver">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-circle-fill text-success" style="font-size: 0.6rem;"></i>
                                        <span>{{ $driver->user->name }}</span>
                                    </div>
                                </td>
                                <td data-label="Car">{{ $driver->car ?? '—' }} {{ $driver->color ? '· '.$driver->color : '' }}</td>
                                <td data-label="Location">{{ number_format($driver->gps_lat, 4) }}, {{ number_format($driver->gps_lng, 4) }}</td>
                                <td data-label="Updated">{{ $driver->gps_updated_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-muted text-center py-3">No drivers online right now.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            'use strict';

            async function refreshOnlineDrivers() {
                try {
                    const response = await fetch('{{ route('drivers.tracking.data') }}', {
                        headers: {
                            Accept: 'application/json',
                        },
                    });
                    const data = await response.json();

                    document.getElementById('onlineDriversCount').textContent = `${data.drivers.length} online`;

                    const tbody = document.querySelector('#onlineDriversTable tbody');
                    if (data.drivers.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-3">No drivers online right now.</td></tr>';
                        return;
                    }

                    tbody.innerHTML = data.drivers.map(driver => `
                        <tr>
                            <td data-label="Driver">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-circle-fill text-success" style="font-size: 0.6rem;"></i>
                                    <span>${driver.name}</span>
                                </div>
                            </td>
                            <td data-label="Car">${driver.car ?? '—'} ${driver.color ? '· ' + driver.color : ''}</td>
                            <td data-label="Location">${driver.lat.toFixed(4)}, ${driver.lng.toFixed(4)}</td>
                            <td data-label="Updated">${driver.updated_at ?? 'just now'}</td>
                        </tr>
                    `).join('');
                } catch (error) {
                    // silently retry on the next poll
                }
            }

            setInterval(refreshOnlineDrivers, 8000);
        })();

        (function() {
            'use strict';
            if (typeof ApexCharts === 'undefined') return;
            const charts = [];

            function cssVar(name) {
                return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            }

            function mount(selector, options) {
                const el = document.querySelector(selector);
                if (!el) return;
                const chart = new ApexCharts(el, options);
                chart.render();
                charts.push(chart);
            }

            function destroyAll() {
                while (charts.length) {
                    const chart = charts.pop();
                    if (chart) chart.destroy();
                }
            }

            function renderAll() {
                const accent = cssVar('--accent-color');
                const success = cssVar('--success-color');
                const warning = cssVar('--warning-color');
                const danger = cssVar('--danger-color');
                const info = cssVar('--info-color');
                const muted = cssVar('--muted-color');
                const border = cssVar('--border-color');
                const spark = (selector, data, color) => mount(selector, {
                    chart: {
                        type: 'line',
                        height: 52,
                        sparkline: {
                            enabled: true
                        },
                        toolbar: {
                            show: false
                        },
                        animations: {
                            enabled: false
                        }
                    },
                    series: [{
                        data
                    }],
                    colors: [color],
                    stroke: {
                        curve: 'smooth',
                        width: 2.2
                    },
                    tooltip: {
                        enabled: false
                    }
                });
                spark('#kpiRevenueChart', [12, 18, 15, 21, 19, 24, 22, 28, 30], accent);
                spark('#kpiCustomersChart', [9, 11, 10, 12, 14, 15, 13, 17, 18], success);
                spark('#kpiOrdersChart', [20, 19, 21, 18, 17, 18, 16, 15, 14], warning);
                spark('#kpiConversionChart', [3.2, 3.7, 3.4, 3.9, 4.2, 4.1, 4.4, 4.6, 4.9], info);
                mount('#laRevenueMainChart', {
                    chart: {
                        type: 'line',
                        height: 260,
                        toolbar: {
                            show: false
                        },
                        animations: {
                            enabled: false
                        }
                    },
                    series: [{
                            name: 'Revenue',
                            data: [52, 56, 54, 62, 66, 64, 72, 74, 79, 84, 86, 90]
                        },
                        {
                            name: 'Cost',
                            data: [31, 33, 34, 38, 40, 41, 44, 46, 49, 52, 53, 55]
                        },
                        {
                            name: 'Profit',
                            data: [21, 23, 20, 24, 26, 23, 28, 28, 30, 32, 33, 35]
                        }
                    ],
                    colors: [accent, warning, success],
                    stroke: {
                        curve: 'smooth',
                        width: [2.8, 2.2, 2.2]
                    },
                    xaxis: {
                        categories: ['W1', 'W2', 'W3', 'W4', 'W5', 'W6', 'W7', 'W8', 'W9', 'W10', 'W11', 'W12'],
                        labels: {
                            style: {
                                colors: muted,
                                fontSize: '12px'
                            }
                        },
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: muted,
                                fontSize: '12px'
                            }
                        }
                    },
                    grid: {
                        borderColor: border,
                        strokeDashArray: 4
                    },
                    legend: {
                        show: false
                    }
                });
                mount('#laTrafficBarsChart', {
                    chart: {
                        type: 'bar',
                        height: 250,
                        toolbar: {
                            show: false
                        },
                        animations: {
                            enabled: false
                        }
                    },
                    series: [{
                            name: 'Organic',
                            data: [22, 26, 24, 31, 34, 37, 35, 41, 44, 42, 47, 49]
                        },
                        {
                            name: 'Paid',
                            data: [14, 16, 15, 18, 22, 24, 25, 27, 30, 28, 31, 35]
                        }
                    ],
                    colors: [accent, info],
                    plotOptions: {
                        bar: {
                            columnWidth: '42%',
                            borderRadius: 2
                        }
                    },
                    xaxis: {
                        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct',
                            'Nov', 'Dec'
                        ],
                        labels: {
                            style: {
                                colors: muted,
                                fontSize: '12px'
                            }
                        },
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: muted,
                                fontSize: '12px'
                            }
                        }
                    },
                    grid: {
                        borderColor: border,
                        strokeDashArray: 4
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'right',
                        labels: {
                            colors: muted
                        }
                    }
                });
                mount('#laTicketHealthChart', {
                    chart: {
                        type: 'donut',
                        height: 250,
                        animations: {
                            enabled: false
                        }
                    },
                    series: [54, 28, 18],
                    labels: ['Resolved', 'In Progress', 'Overdue'],
                    colors: [success, accent, danger],
                    legend: {
                        position: 'bottom',
                        labels: {
                            colors: muted
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        width: 2,
                        colors: [cssVar('--surface-color')]
                    }
                });
            }

            function rerender() {
                destroyAll();
                renderAll();
            }
            renderAll();
            document.addEventListener('themeChanged', rerender);
            const observer = new MutationObserver(function(mutations) {
                for (let i = 0; i < mutations.length; i += 1) {
                    if (mutations[i].attributeName === 'data-theme') {
                        rerender();
                        break;
                    }
                }
            });
            observer.observe(document.documentElement, {
                attributes: true
            });
        })();
    </script>
@endpush
