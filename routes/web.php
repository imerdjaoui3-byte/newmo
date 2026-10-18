<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\CheckOrderPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispatchOrderController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\DriverLoadController;
use App\Http\Controllers\DriverLocationController;
use App\Http\Controllers\DriverOrderStatusController;
use App\Http\Controllers\DriverTrackingController;
use App\Http\Controllers\DriverTrackingSetupController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\MarkOrderPaidController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductStockController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReturnDriverLoadsController;
use App\Http\Controllers\SmsWebhookController;
use App\Http\Controllers\UnassignOrderController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

// routes/web.php
Route::get('/gps-test-send', function () {
    return view('gps-test-send');
});

Route::post('/sms/webhook', [SmsWebhookController::class, 'receive'])
    ->name('sms.webhook')
    ->withoutMiddleware([VerifyCsrfToken::class]);

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/auth/google/redirect', [GoogleLoginController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleLoginController::class, 'callback'])->name('google.callback');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::resource('drivers', DriverController::class)->except(['show']);
    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('products', ProductController::class)->except(['show']);
    Route::patch('/products/{product}/stock', ProductStockController::class)->name('products.stock');
    Route::resource('orders', OrderController::class)->except(['show']);
    Route::post('/orders/{order}/dispatch', DispatchOrderController::class)->name('orders.dispatch');
    Route::post('/orders/{order}/unassign', UnassignOrderController::class)->name('orders.unassign');
    Route::post('/orders/{order}/check-payment', CheckOrderPaymentController::class)->name('orders.check-payment');
    Route::post('/orders/{order}/mark-paid', MarkOrderPaidController::class)->name('orders.mark-paid');

    Route::get('/driver-loads', [DriverLoadController::class, 'index'])->name('driver-loads.index');
    Route::put('/driver-loads', [DriverLoadController::class, 'update'])->name('driver-loads.update');

    Route::get('/tracking', [DriverTrackingController::class, 'index'])->name('drivers.tracking');
    Route::get('/tracking/data', [DriverTrackingController::class, 'data'])->name('drivers.tracking.data');

    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    Route::post('/driver/location', [DriverLocationController::class, 'update'])->name('driver.location.update');
    Route::patch('/driver/orders/{order}/status', DriverOrderStatusController::class)->name('driver.orders.status');
    Route::post('/driver/loads/return', ReturnDriverLoadsController::class)->name('driver.loads.return');
    Route::post('/driver/tracking-setup', DriverTrackingSetupController::class)->name('driver.tracking.setup');

    Route::get('/bills', [BillController::class, 'index'])->name('bills.index');

    Route::get('/mailbox', [MailboxController::class, 'show'])->name('mailbox.show');
    Route::get('/mailbox/connect', [MailboxController::class, 'connect'])->name('mailbox.connect');
    Route::get('/mailbox/callback', [MailboxController::class, 'callback'])->name('mailbox.callback');
    Route::delete('/mailbox', [MailboxController::class, 'destroy'])->name('mailbox.destroy');

    // Add new routes here — everything in this group requires login.
});
