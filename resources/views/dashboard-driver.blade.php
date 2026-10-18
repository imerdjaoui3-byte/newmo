@extends('layouts.app')

@section('title', 'Dashboard')

@section('content-class', 'page-dashboard')

@section('content')
    <section class="card">
        <div class="card-body text-center py-5">
            <h4 class="mb-2">Welcome back, {{ auth()->user()->name }}</h4>
            <p class="text-muted mb-3">Have a safe trip today.</p>
            <span class="la-badge la-badge-info" id="locationStatus">Location: checking…</span>
        </div>
    </section>

    @if ($driverProfile = auth()->user()->driver)
        <section class="card mt-4 mb-0">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-0">Background Tracking</h5>
                    <span class="text-muted small">
                        @if ($driverProfile->hasTrackingApp())
                            OwnTracks connected{{ $driverProfile->gps_updated_at ? ' · last location '.$driverProfile->gps_updated_at->diffForHumans() : '' }}
                        @else
                            Share your location even when this page is closed, using the free OwnTracks app
                        @endif
                    </span>
                </div>
                <form method="POST" action="{{ route('driver.tracking.setup') }}"
                    @if ($driverProfile->hasTrackingApp())
                        data-confirm-form data-confirm-title="Set up OwnTracks again?"
                        data-confirm-text="The phone currently connected will stop sending your location until it is set up with the new link."
                        data-confirm-button="Set up again"
                    @endif>
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">
                        <i class="bi bi-broadcast me-1"></i>{{ $driverProfile->hasTrackingApp() ? 'Set up again' : 'Set up OwnTracks' }}
                    </button>
                </form>
            </div>
            @if ($trackingSetup = session('trackingSetup'))
                <div class="card-body">
                    <ol class="small mb-3 ps-3">
                        <li>Install OwnTracks:
                            <a href="https://apps.apple.com/app/owntracks/id692424691" target="_blank" rel="noopener">iPhone</a> ·
                            <a href="https://play.google.com/store/apps/details?id=org.owntracks.android" target="_blank" rel="noopener">Android</a>
                        </li>
                        <li>On this phone, tap the button below and accept the configuration.</li>
                        <li>Allow location access <strong>"Always"</strong> (Android: also turn off battery optimisation for OwnTracks).</li>
                    </ol>
                    <a href="{{ $trackingSetup['link'] }}" class="btn btn-primary w-100 mb-3">
                        <i class="bi bi-phone me-1"></i>Open in OwnTracks
                    </a>
                    <details class="small">
                        <summary class="text-muted">Set it up manually instead</summary>
                        <p class="mt-2 mb-1">In OwnTracks settings choose mode <strong>HTTP</strong> and enter:</p>
                        <dl class="row mb-0">
                            <dt class="col-4">URL</dt><dd class="col-8 text-break"><code>{{ $trackingSetup['url'] }}</code></dd>
                            <dt class="col-4">Username</dt><dd class="col-8 text-break"><code>{{ $trackingSetup['username'] }}</code></dd>
                            <dt class="col-4">Password</dt><dd class="col-8 text-break"><code>{{ $trackingSetup['password'] }}</code></dd>
                        </dl>
                        <p class="text-muted mt-2 mb-0">This password is only shown once.</p>
                    </details>
                </div>
            @endif
        </section>
    @endif

    @if ($todaysLoads->isNotEmpty())
        <section class="card mt-4 mb-0">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-0">Today's Stock</h5>
                    @if ($todaysLoads->first()->isReturned())
                        <span class="text-muted small">Returned to the warehouse at {{ $todaysLoads->first()->returned_at->format('g:i A') }}</span>
                    @endif
                </div>
                @unless ($todaysLoads->first()->isReturned())
                    <form method="POST" action="{{ route('driver.loads.return') }}"
                        data-confirm-form data-confirm-title="Return your stock?"
                        data-confirm-text="Everything you did not sell goes back into the warehouse, and you can't sell anything else today."
                        data-confirm-button="Return">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-warning text-nowrap">
                            <i class="bi bi-box-arrow-in-left me-1"></i>Return
                        </button>
                    </form>
                @endunless
            </div>
            <div class="card-body">
                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif
                <table class="table table-sm table-stack mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Loaded</th>
                            <th class="text-end">Sold</th>
                            <th class="text-end">Left</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($todaysLoads as $load)
                            <tr>
                                <td data-label="Product">{{ $load->product->name }}</td>
                                <td class="text-end" data-label="Loaded">{{ $load->quantity }}</td>
                                <td class="text-end" data-label="Sold">{{ $soldToday[$load->product_id] ?? 0 }}</td>
                                <td class="text-end" data-label="Left"><strong>{{ $load->remaining($soldToday[$load->product_id] ?? 0) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="d-flex align-items-center justify-content-between mt-4 mb-3">
        <h5 class="mb-0">My Orders</h5>
        <span class="text-muted small">{{ $assignedOrders->count() }} open</span>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="d-grid gap-3" id="assignedOrders">
        @forelse ($assignedOrders as $order)
            <section class="card order-row mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <span class="order-row-avatar">
                                @if ($order->customer->initials())
                                    {{ $order->customer->initials() }}
                                @else
                                    <i class="bi bi-person"></i>
                                @endif
                            </span>
                            <div class="min-w-0">
                                <div class="order-row-name">{{ $order->customer->name ?? 'Unknown customer' }}</div>
                            </div>
                        </div>
                        <span class="order-row-status order-row-status-{{ $order->statusBadgeVariant() }}">
                            Status: {{ str_replace('_', ' ', $order->status) }}
                        </span>
                    </div>

                    <div class="d-flex align-items-end justify-content-between flex-wrap gap-3 mt-3">
                        <div class="min-w-0">
                            <div class="order-row-label">Shipping Address</div>
                            <a href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($order->address) }}"
                                target="_blank" rel="noopener" class="order-row-address">
                                <i class="bi bi-geo-alt-fill me-1"></i>{{ $order->address }}
                            </a>
                        </div>

                        <div class="d-flex flex-wrap gap-2 order-row-actions">
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal"
                                data-bs-target="#paymentModal" data-action="{{ route('driver.orders.status', $order) }}">
                                <i class="bi bi-check-lg me-1"></i>Done
                            </button>
                            @if ($order->status === 'dispatched')
                                <form method="POST" action="{{ route('driver.orders.status', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" name="status" value="in_route" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-truck me-1"></i>In route
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('driver.orders.status', $order) }}"
                                data-confirm-form data-confirm-title="Customer didn't show?"
                                data-confirm-text="This order will be closed as a no show." data-confirm-button="No show">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="no_show">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-person-x me-1"></i>No show
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        @empty
            <section class="card mb-0">
                <div class="card-body text-center text-muted py-4">No orders yet.</div>
            </section>
        @endforelse
    </div>

    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content" id="paymentForm">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="done">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">How was it paid?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex gap-2 mb-3">
                        <input type="radio" class="btn-check" name="payment_method" id="paymentEmt" value="emt" required>
                        <label class="btn btn-outline-primary flex-fill" for="paymentEmt">EMT</label>
                        <input type="radio" class="btn-check" name="payment_method" id="paymentCash" value="cash" required>
                        <label class="btn btn-outline-primary flex-fill" for="paymentCash">Cash</label>
                    </div>
                    <div class="d-none" id="paymentReferenceGroup">
                        <label for="payment_reference" class="form-label">EMT transaction ID</label>
                        <input type="text" class="form-control" id="payment_reference" name="payment_reference" maxlength="255">
                    </div>

                    <hr>

                    <h6 class="mb-2">Rate the customer</h6>
                    <div class="d-flex gap-1 mb-3" role="radiogroup" aria-label="Customer rating">
                        @foreach (range(1, 5) as $stars)
                            <input type="radio" class="btn-check" name="customer_rating" id="customerRating{{ $stars }}"
                                value="{{ $stars }}" required>
                            <label class="btn btn-outline-warning flex-fill" for="customerRating{{ $stars }}">{{ $stars }} <i class="bi bi-star-fill"></i></label>
                        @endforeach
                    </div>

                    <hr>

                    <h6 class="mb-2">What was sold?</h6>
                    @if ($todaysLoads->isEmpty())
                        <div class="alert alert-warning small mb-0">No stock was loaded for you today. Ask dispatch to add your stock.</div>
                    @else
                        <div class="d-grid gap-2" id="soldItems"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="addSoldItem">
                            <i class="bi bi-plus-lg me-1"></i>Add product
                        </button>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" @disabled($todaysLoads->isEmpty())>Mark as done</button>
                </div>
            </form>
        </div>
    </div>

    <template id="soldItemTemplate">
        <div class="d-flex gap-2" data-sold-item>
            <select class="form-select form-select-sm" data-field="product_id" required aria-label="Product">
                <option value="">Select a product</option>
                @foreach ($todaysLoads as $load)
                    @php($remaining = $load->remaining($soldToday[$load->product_id] ?? 0))
                    <option value="{{ $load->product_id }}" @disabled($remaining < 1)>
                        {{ $load->product->name }} ({{ $remaining }} left)
                    </option>
                @endforeach
            </select>
            <input type="number" class="form-control form-control-sm" data-field="quantity" min="1" step="1" value="1"
                required style="max-width: 6rem" aria-label="Quantity">
            <button type="button" class="btn btn-sm btn-icon btn-outline-danger" data-remove-sold-item aria-label="Remove product">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </template>

    <form id="autoLogoutForm" method="POST" action="{{ route('logout') }}" class="d-none">
        @csrf
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusEl = document.getElementById('locationStatus');

            const paymentModal = document.getElementById('paymentModal');
            const paymentForm = document.getElementById('paymentForm');
            const referenceGroup = document.getElementById('paymentReferenceGroup');
            const referenceInput = document.getElementById('payment_reference');

            const soldItems = document.getElementById('soldItems');
            const soldItemTemplate = document.getElementById('soldItemTemplate');
            let soldItemCount = 0;

            function addSoldItem() {
                const row = soldItemTemplate.content.firstElementChild.cloneNode(true);
                row.querySelectorAll('[data-field]').forEach((field) => {
                    field.name = `items[${soldItemCount}][${field.dataset.field}]`;
                });
                soldItemCount++;
                soldItems.appendChild(row);
            }

            if (soldItems) {
                document.getElementById('addSoldItem').addEventListener('click', addSoldItem);
                soldItems.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-remove-sold-item]');
                    if (removeButton && soldItems.children.length > 1) {
                        removeButton.closest('[data-sold-item]').remove();
                    }
                });
            }

            paymentModal.addEventListener('show.bs.modal', (event) => {
                paymentForm.action = event.relatedTarget.dataset.action;
                paymentForm.reset();
                referenceGroup.classList.add('d-none');
                referenceInput.required = false;
                if (soldItems) {
                    soldItems.replaceChildren();
                    addSoldItem();
                }
            });

            paymentForm.querySelectorAll('[name="payment_method"]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    const isEmt = radio.value === 'emt';
                    referenceGroup.classList.toggle('d-none', !isEmt);
                    referenceInput.required = isEmt;
                    if (isEmt) {
                        referenceInput.focus();
                    }
                });
            });

            if (window.Echo) {
                window.Echo.private('App.Models.User.{{ auth()->id() }}')
                    .listen('OrderDispatched', () => {
                        // Reload so the new order comes with its status buttons.
                        window.location.reload();
                    })
                    .listen('OrderUnassigned', () => {
                        // Reload so the cancelled order disappears.
                        window.location.reload();
                    });

                // Dispatch sees this driver go offline the moment this page closes.
                window.Echo.join('drivers.online');
            }

            if (!navigator.geolocation) {
                statusEl.textContent = 'Location: not supported';
                return;
            }

            function sendLocation(position) {
                axios.post('{{ route('driver.location.update') }}', {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                }).then(() => {
                    statusEl.textContent = 'Location: sharing';
                }).catch(() => {
                    statusEl.textContent = 'Location: error sending';
                });
            }

            function handleError(error) {
                if (error.code === error.PERMISSION_DENIED) {
                    statusEl.textContent = 'Location: permission denied — signing out…';
                    document.getElementById('autoLogoutForm').submit();
                    return;
                }

                statusEl.textContent = 'Location: unavailable';
            }

            navigator.geolocation.getCurrentPosition(sendLocation, handleError);
            setInterval(() => navigator.geolocation.getCurrentPosition(sendLocation, handleError), 10000);
        });
    </script>
@endpush
