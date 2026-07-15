<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\SubconAdminController;
use App\Http\Controllers\SubconApprovalController;
use App\Http\Controllers\SubconVendorController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Auth::routes();

// Override default /home route
Route::get('/home', function () {
    if (Auth::check()) {
        $role = Auth::user()->role;

        return match ($role) {
            'admin', 'fabric_admin' => redirect()->route('admin.dashboard'),
            'fabric_vendor' => redirect()->route('vendor.dashboard'),
            'subcon_admin' => redirect()->route('subcon.admin.dashboard'),
            'subcon_vendor' => redirect()->route('subcon.vendor.dashboard'),
            default => redirect('/'),
        };
    }

    return redirect('/');
})->name('home');

// Home Route
Route::get('/', function () {
    if (auth()->check()) {
        $role = auth()->user()->role;

        return match ($role) {
            'admin', 'fabric_admin' => redirect()->route('admin.dashboard'),
            'fabric_vendor' => redirect()->route('vendor.dashboard'),
            'subcon_admin' => redirect()->route('subcon.admin.dashboard'),
            'subcon_vendor' => redirect()->route('subcon.vendor.dashboard'),
            default => redirect()->route('login'),
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
    Route::post('/item/{id}/upload-rolls', [AdminController::class, 'uploadRollsData'])->name('item.upload-rolls');
    Route::get('/item/{id}/rolls-template', [AdminController::class, 'downloadRollsTemplate'])->name('item.rolls-template');
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
    Route::get('/item/{id}/rolls-template', [VendorController::class, 'downloadRollsTemplate'])->name('item.rolls-template');
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
// Relative signature (path + query only) so it survives the HTTPS reverse proxy.
Route::get('/tolerance/approve/{request}', [App\Http\Controllers\ApprovalController::class, 'approveAmendment'])->name('tolerance.approve')->middleware('signed:relative');
Route::get('/tolerance/decline/{request}', [App\Http\Controllers\ApprovalController::class, 'declineAmendment'])->name('tolerance.decline')->middleware('signed:relative');

// Subcon Admin Routes
Route::middleware(['auth'])->prefix('subcon/admin')->name('subcon.admin.')->group(function () {
    Route::get('/dashboard', [SubconAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/logs', [SubconAdminController::class, 'logs'])->name('logs');
    Route::post('/sync-orders', [SubconAdminController::class, 'syncOrders'])->name('sync-orders');
    Route::get('/sync-status', [SubconAdminController::class, 'syncStatus'])->name('sync-status');
    Route::get('/vendors', [SubconAdminController::class, 'vendors'])->name('vendors');
    Route::post('/vendors', [SubconAdminController::class, 'storeVendor'])->name('vendor.store');
    Route::put('/vendors/{id}', [SubconAdminController::class, 'updateVendor'])->name('vendors.update');
    Route::post('/vendors/{id}/toggle-status', [SubconAdminController::class, 'toggleVendorStatus'])->name('vendors.toggle-status');
    Route::delete('/vendors/{id}', [SubconAdminController::class, 'deleteVendor'])->name('vendors.destroy');
    Route::get('/approvals', [SubconAdminController::class, 'approvals'])->name('approvals');
    Route::get('/director-approvals', [SubconAdminController::class, 'directorApprovals'])->name('director-approvals');
    Route::get('/approval-logs', [SubconAdminController::class, 'approvalLogs'])->name('approval-logs');
    Route::get('/orders', [SubconAdminController::class, 'orders'])->name('orders');
    Route::get('/orders-waiting-distribution', [SubconAdminController::class, 'waitingDistribution'])->name('orders-waiting-distribution');
    Route::post('/orders/{id}/generate-labels-manual', [SubconAdminController::class, 'generateLabelsManual'])->name('orders.generate-labels-manual');
    Route::get('/orders/{id}', [SubconAdminController::class, 'viewOrder'])->name('orders.view');
    Route::get('/orders/{id}/export-cutting', [SubconAdminController::class, 'exportCuttingReport'])->name('orders.export-cutting');
    Route::post('/orders/{id}/status', [SubconAdminController::class, 'updateOrderStatus'])->name('orders.update-status');
    Route::post('/orders/{id}/approve', [SubconApprovalController::class, 'approveInApp'])->name('orders.approve');
    Route::post('/orders/{id}/decline', [SubconApprovalController::class, 'declineInApp'])->name('orders.decline');
    Route::get('/orders/{id}/print-labels', [SubconAdminController::class, 'printPackagingLabels'])->name('orders.print-labels');
    Route::get('/workflow', [SubconAdminController::class, 'workflow'])->name('workflow');
    Route::post('/workflow', [SubconAdminController::class, 'updateWorkflow'])->name('workflow.update');
});

// Subcon Approval Routes (Signed URLs from approval emails)
// Relative signature (path + query only) so it survives the HTTPS reverse proxy
// — the absolute host/scheme the app sees internally differs from the public URL.
Route::get('/subcon/approve/{order}/{gate}', [SubconApprovalController::class, 'approveSigned'])->name('subcon.approve')->middleware('signed:relative');
// No-login cutting-approval form submit (consumption + approve). Signed POST.
Route::post('/subcon/approve-cutting/{order}', [SubconApprovalController::class, 'approveCuttingSubmit'])->name('subcon.approve.cutting.submit')->middleware('signed:relative');
Route::get('/subcon/decline/{order}/{gate}', [SubconApprovalController::class, 'declineSigned'])->name('subcon.decline')->middleware('signed:relative');
Route::get('/subcon/generate-labels/{order}', [SubconApprovalController::class, 'generateLabelsSigned'])->name('subcon.generate-labels')->middleware('signed:relative');

// QC Console packaging-approval link (from the Tauri console's email).
// Public route: factory reps have no portal login; the random `approval_token`
// stored on the QMS session row IS the credential. GET so an email click works.
Route::get('/qc/approve/{token}', [App\Http\Controllers\QcApprovalController::class, 'approve'])->name('qc.approve');
Route::get('/qc/reject/{token}', [App\Http\Controllers\QcApprovalController::class, 'reject'])->name('qc.reject');

// HO (Head Office) approval — second stage, emailed to our approver list after
// the vendor confirms. Same `approval_token` (the contract keys both stages off
// it); GET renders the form, POST commits fabric/deduction lines + the HO signature.
Route::get('/qc/ho-approve/{token}', [App\Http\Controllers\QcApprovalController::class, 'hoApprovalForm'])->name('qc.ho-approve');
Route::post('/qc/ho-approve/{token}', [App\Http\Controllers\QcApprovalController::class, 'hoApprove'])->name('qc.ho-approve.submit');
// HO rejection — writes `ho_approval_signature` with a "Rejected: …" prefix (console contract).
// GET only renders a confirmation page; the actual write is a POST. This is deliberate:
// the HO email goes to corporate mailboxes whose link scanners (Microsoft Safe Links /
// antivirus) prefetch every URL via GET — a mutating GET was auto-rejecting every order
// within a second of send. A human must click through the confirm page to POST.
Route::get('/qc/ho-decline/{token}', [App\Http\Controllers\QcApprovalController::class, 'hoDeclineForm'])->name('qc.ho-decline');
Route::post('/qc/ho-decline/{token}', [App\Http\Controllers\QcApprovalController::class, 'hoDecline'])->name('qc.ho-decline.submit');

// Director authorization — third stage, emailed after MD Production approves.
// Same `approval_token`. Read-only review form (GET) + sign-off (POST); approval
// queues the Invoice/Deduction RPA jobs and completes the project. Rejection goes
// back to MD Production (not to QC) — same POST-behind-confirm-page scanner guard.
Route::get('/qc/director-approve/{token}', [App\Http\Controllers\QcApprovalController::class, 'directorApprovalForm'])->name('qc.director-approve');
Route::post('/qc/director-approve/{token}', [App\Http\Controllers\QcApprovalController::class, 'directorApprove'])->name('qc.director-approve.submit');
Route::get('/qc/director-decline/{token}', [App\Http\Controllers\QcApprovalController::class, 'directorDeclineForm'])->name('qc.director-decline');
Route::post('/qc/director-decline/{token}', [App\Http\Controllers\QcApprovalController::class, 'directorDecline'])->name('qc.director-decline.submit');

// Current signed inspection PDF (verified_doc) for a session — same token gate.
Route::get('/qc/document/{token}', [App\Http\Controllers\QcApprovalController::class, 'document'])->name('qc.document');

// Server-side PDF generation for active or draft session report.
Route::get('/qc/print/{projectId}/{sessionId}', [App\Http\Controllers\QcApprovalController::class, 'printDraft'])->name('qc.print-draft');

// Subcon Vendor Routes
Route::middleware(['auth'])->prefix('subcon/vendor')->name('subcon.vendor.')->group(function () {
    Route::get('/dashboard', [SubconVendorController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders', [SubconVendorController::class, 'orders'])->name('orders');
    Route::get('/orders/{id}', [SubconVendorController::class, 'viewOrder'])->name('orders.view');
    Route::post('/orders/{id}/remarks', [SubconVendorController::class, 'saveRemarks'])->name('orders.remarks');
    Route::post('/orders/{id}/submit-cutting', [SubconVendorController::class, 'submitCuttingReport'])->name('orders.submit-cutting');
    Route::post('/orders/{id}/submit-gramasi', [SubconVendorController::class, 'submitGramasi'])->name('orders.submit-gramasi');
    Route::post('/orders/{id}/complete', [SubconVendorController::class, 'completeOrder'])->name('orders.complete');
    Route::get('/orders/{id}/template', [SubconVendorController::class, 'downloadTemplate'])->name('orders.download-template');
    Route::post('/orders/{id}/upload-report', [SubconVendorController::class, 'uploadReport'])->name('orders.upload-report');
    Route::get('/orders/{id}/print-labels', [SubconVendorController::class, 'printPackagingLabels'])->name('orders.print-labels');
    Route::get('/profile', [SubconVendorController::class, 'profile'])->name('profile');
    Route::put('/profile', [SubconVendorController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [SubconVendorController::class, 'updatePassword'])->name('profile.password');
});

// Notification Routes
Route::post('/notifications/mark-as-read', function () {
    auth()->user()->unreadNotifications->each->markAsRead();

    return response()->json(['success' => true]);
})->name('notifications.mark-as-read')->middleware('auth');
