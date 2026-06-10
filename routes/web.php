<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\SubconAdminController;
use App\Http\Controllers\SubconVendorController;

// Authentication Routes
Auth::routes();

// Override default /home route
Route::get('/home', function () {
    if (Auth::check()) {
        $role = Auth::user()->role;
        return match($role) {
            'admin', 'fabric_admin' => redirect()->route('admin.dashboard'),
            'fabric_vendor'         => redirect()->route('vendor.dashboard'),
            'subcon_admin'          => redirect()->route('subcon.admin.dashboard'),
            'subcon_vendor'         => redirect()->route('subcon.vendor.dashboard'),
            default                 => redirect('/'),
        };
    }
    return redirect('/');
})->name('home');

// Home Route
Route::get('/', function () {
    if (auth()->check()) {
        $role = auth()->user()->role;
        return match($role) {
            'admin', 'fabric_admin' => redirect()->route('admin.dashboard'),
            'fabric_vendor'         => redirect()->route('vendor.dashboard'),
            'subcon_admin'          => redirect()->route('subcon.admin.dashboard'),
            'subcon_vendor'         => redirect()->route('subcon.vendor.dashboard'),
            default                 => redirect()->route('login'),
        };
    }
    return redirect()->route('login');
});

// Admin Routes
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/purchase-orders', [AdminController::class, 'purchaseOrders'])->name('purchase-orders');
    Route::get('/purchase-order/{id}', [AdminController::class, 'viewPurchaseOrder'])->name('purchase-order.view');
    Route::put('/purchase-order/{id}', [AdminController::class, 'updatePurchaseOrder'])->name('purchase-order.update');
    Route::put('/vendor/{id}', [AdminController::class, 'updateVendor'])->name('vendor.update');
    Route::post('/vendors', [AdminController::class, 'storeVendor'])->name('vendor.store');
    Route::get('/vendors', [AdminController::class, 'vendors'])->name('vendors');
    Route::get('/vendor/{id}', [AdminController::class, 'vendorDetail'])->name('vendor.detail');

    // Admin Item Processing Routes (Mirrors Vendor)
    Route::get('/item/{id}/process', [AdminController::class, 'processItem'])->name('item.process');
    Route::post('/item/{id}/save-rolls', [AdminController::class, 'saveItemRolls'])->name('item.save-rolls');
    Route::post('/rolls/{roll}/delete', [AdminController::class, 'deleteRoll'])->name('roll.delete');
    Route::post('/item/{id}/mark-processed', [AdminController::class, 'markItemProcessed'])->name('item.mark-processed');
    Route::post('/item/{id}/mark-partial', [AdminController::class, 'markAsPartialShipment'])->name('item.mark-partial');
    Route::post('/item/{id}/revert-processing', [AdminController::class, 'revertProcessing'])->name('item.revert-processing');

    // Admin Packing Slips
    Route::post('/purchase-order/{id}/generate-slip', [AdminController::class, 'generatePackingSlip'])->name('generate-packing-slip');
    Route::get('/packing-slip/{id}', [AdminController::class, 'viewPackingSlip'])->name('packing-slip.view');
    Route::get('/packing-slip/{id}/print', [AdminController::class, 'printPackingSlip'])->name('packing-slip.print');

    // Admin Quick Packing Slip
    Route::post('/purchase-order/{id}/quick-packing-slip', [AdminController::class, 'quickGeneratePackingSlip'])->name('quick-packing-slip');

    // Admin Settings
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
});

// Vendor Routes
Route::middleware(['auth'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/dashboard', [VendorController::class, 'dashboard'])->name('dashboard');
    Route::get('/purchase-orders', [VendorController::class, 'purchaseOrders'])->name('purchase-orders');
    Route::get('/purchase-order/{id}', [VendorController::class, 'viewPurchaseOrder'])->name('purchase-order.view');

    // Item processing routes
    Route::get('/item/{id}/process', [VendorController::class, 'processItem'])->name('item.process');
    Route::post('/item/{id}/save-rolls', [VendorController::class, 'saveItemRolls'])->name('item.save-rolls');
    Route::post('/item/{id}/upload-rolls', [VendorController::class, 'uploadRollsData'])->name('item.upload-rolls');
    Route::post('/rolls/{roll}/delete', [VendorController::class, 'deleteRoll'])->name('vendor.roll.delete');
    Route::post('/item/{id}/mark-processed', [VendorController::class, 'markItemProcessed'])->name('item.mark-processed');
    Route::post('/item/{id}/mark-partial', [VendorController::class, 'markAsPartialShipment'])->name('item.mark-partial');
    Route::post('/item/{id}/revert-processing', [VendorController::class, 'revertProcessing'])->name('item.revert-processing');

    // Packing slips
    Route::post('/purchase-order/{id}/generate-slip', [VendorController::class, 'generatePackingSlip'])->name('generate-packing-slip');
    Route::get('/packing-slip/{id}', [VendorController::class, 'viewPackingSlip'])->name('packing-slip.view');
    Route::get('/packing-slip/{id}/print', [VendorController::class, 'printPackingSlip'])->name('packing-slip.print');

    // Quick Packing Slip
    Route::post('/purchase-order/{id}/quick-packing-slip', [VendorController::class, 'quickGeneratePackingSlip'])->name('quick-packing-slip');

    // Tolerance Amendment & Partial Shipment Requests
    Route::post('/tolerance/amend', [VendorController::class, 'requestToleranceAmendment'])->name('tolerance.amend');
    Route::post('/partial/request', [VendorController::class, 'requestPartialShipment'])->name('partial.request');
});

// Approval Routes (Signed URLs)
Route::get('/tolerance/approve/{request}', [App\Http\Controllers\ApprovalController::class, 'approveAmendment'])->name('tolerance.approve')->middleware('signed');
Route::get('/tolerance/decline/{request}', [App\Http\Controllers\ApprovalController::class, 'declineAmendment'])->name('tolerance.decline')->middleware('signed');

// Subcon Admin Routes
Route::middleware(['auth'])->prefix('subcon/admin')->name('subcon.admin.')->group(function () {
    Route::get('/dashboard', [SubconAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/vendors', [SubconAdminController::class, 'vendors'])->name('vendors');
    Route::post('/vendors', [SubconAdminController::class, 'storeVendor'])->name('vendor.store');
    Route::get('/orders', [SubconAdminController::class, 'orders'])->name('orders');
    Route::get('/orders/create', [SubconAdminController::class, 'createOrder'])->name('orders.create');
    Route::post('/orders', [SubconAdminController::class, 'storeOrder'])->name('orders.store');
    Route::get('/orders/{id}', [SubconAdminController::class, 'viewOrder'])->name('orders.view');
    Route::post('/orders/{id}/status', [SubconAdminController::class, 'updateOrderStatus'])->name('orders.update-status');
});

// Subcon Vendor Routes
Route::middleware(['auth'])->prefix('subcon/vendor')->name('subcon.vendor.')->group(function () {
    Route::get('/dashboard', [SubconVendorController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders', [SubconVendorController::class, 'orders'])->name('orders');
    Route::get('/orders/{id}', [SubconVendorController::class, 'viewOrder'])->name('orders.view');
});

// Notification Routes
Route::post('/notifications/mark-as-read', function () {
    auth()->user()->unreadNotifications->each->markAsRead();
    return response()->json(['success' => true]);
})->name('notifications.mark-as-read')->middleware('auth');