@extends('layouts.app')

@section('title', 'Driver Tracking')

@section('content-class', 'page-dashboard')

@push('styles')
    <link href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" rel="stylesheet">
    <style>
        .driver-marker-label {
            padding: 3px 7px;
            text-align: center;
        }

        .driver-marker-name {
            font-weight: 600;
            font-size: 0.75rem;
            line-height: 1.2;
        }

        .driver-marker-time {
            font-size: 0.65rem;
            color: var(--muted-color, #6c757d);
            line-height: 1.2;
        }
    </style>
@endpush

@section('content')
    <section class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-1">Live Driver Tracking</h5>
                <p class="text-muted small mb-0">Drivers currently logged in and their last known location</p>
            </div>
            <span class="la-badge la-badge-info" id="trackingCount">0 online</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div id="trackingMap" style="height: 520px; border-radius: var(--radius-md, 8px);"></div>
                </div>
                <div class="col-lg-4">
                    <div id="trackingList" class="d-flex flex-column gap-2" style="max-height: 520px; overflow-y: auto;">
                        <p class="text-muted small mb-0" id="trackingEmpty">No drivers online right now.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendors/leaflet/leaflet.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const map = L.map('trackingMap').setView([31.7917, -7.0926], 6);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        map.setView([position.coords.latitude, position.coords.longitude], 12);
                    },
                    () => {
                        // permission denied or unavailable, keep default view
                    }
                );
            }

            const markers = new Map();
            const listEl = document.getElementById('trackingList');
            const emptyEl = document.getElementById('trackingEmpty');
            const countEl = document.getElementById('trackingCount');

            function renderList(drivers) {
                listEl.querySelectorAll('[data-driver-card]').forEach(el => el.remove());

                if (drivers.length === 0) {
                    emptyEl.hidden = false;
                    return;
                }
                emptyEl.hidden = true;

                drivers.forEach(driver => {
                    const card = document.createElement('div');
                    card.className = 'card';
                    card.dataset.driverCard = driver.id;
                    card.innerHTML = `
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <strong>${driver.name}</strong>
                                <i class="bi bi-circle-fill text-success" style="font-size: 0.6rem;" title="Online"></i>
                            </div>
                            <div class="text-muted small">${driver.car ?? 'No car set'} ${driver.color ? '· ' + driver.color : ''}</div>
                            <div class="text-muted small">Updated ${driver.updated_at ?? 'just now'}</div>
                        </div>
                    `;
                    card.style.cursor = 'pointer';
                    card.addEventListener('click', () => {
                        map.setView([driver.lat, driver.lng], 15);
                        markers.get(driver.id)?.openPopup();
                    });
                    listEl.appendChild(card);
                });
            }

            function syncMarkers(drivers) {
                const seenIds = new Set();

                drivers.forEach(driver => {
                    seenIds.add(driver.id);
                    const latLng = [driver.lat, driver.lng];

                    if (markers.has(driver.id)) {
                        markers.get(driver.id).setLatLng(latLng);
                    } else {
                        const marker = L.marker(latLng).addTo(map);
                        marker.bindTooltip('', {
                            permanent: true,
                            direction: 'top',
                            offset: [0, -36],
                            className: 'driver-marker-label',
                        });
                        markers.set(driver.id, marker);
                    }

                    markers.get(driver.id).setTooltipContent(
                        `<div class="driver-marker-name">${driver.name}</div><div class="driver-marker-time">${driver.updated_at ?? 'just now'}</div>`
                    );

                    markers.get(driver.id).bindPopup(
                        `<strong>${driver.name}</strong><br>${driver.car ?? ''} ${driver.color ?? ''}<br>Updated ${driver.updated_at ?? 'just now'}`
                    );
                });

                markers.forEach((marker, id) => {
                    if (!seenIds.has(id)) {
                        map.removeLayer(marker);
                        markers.delete(id);
                    }
                });
            }

            const drivers = new Map();

            function render() {
                const list = [...drivers.values()];

                countEl.textContent = `${list.length} online`;
                syncMarkers(list);
                renderList(list);
            }

            async function refresh() {
                try {
                    const response = await fetch('{{ route('drivers.tracking.data') }}', {
                        headers: {
                            Accept: 'application/json',
                        },
                    });
                    const data = await response.json();

                    drivers.clear();
                    data.drivers.forEach(driver => drivers.set(driver.id, driver));
                    render();
                } catch (error) {
                    // silently retry on the next poll
                }
            }

            refresh();

            if (window.Echo) {
                // Live positions arrive over Reverb; the slow refresh only drops drivers who logged out.
                window.Echo.private('drivers.tracking')
                    .listen('DriverLocationUpdated', (event) => {
                        drivers.set(event.driver.id, event.driver);
                        render();
                    });

                setInterval(refresh, 60000);
            } else {
                setInterval(refresh, 8000);
            }
        });
    </script>
@endpush
