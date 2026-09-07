<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\FinanceAdminController;
use App\Http\Controllers\RpaFinalizeController;
use App\Http\Controllers\SubconAdminController;
use App\Http\Controllers\SubconApprovalController;
use App\Http\Controllers\SubconCuttingPlanController;
use App\Http\Controllers\SubconVendorController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Auth::routes();

// Machine-to-machine — no session/CSRF, own shared-secret auth (see
// RpaFinalizeController + this route's CSRF exemption in bootstrap/app.php).
Route::post('/rpa/deduction/{id}/finalize', [RpaFinalizeController::class, 'finalize'])->name('rpa.deduction.finalize');

// Override default /home route
Route::get('/home', function () {
    if (Auth::check()) {
        $role = Auth::user()->role;

        return match ($role) {
            'admin', 'fabric_admin' => redirect()->route('admin.dashboard'),
            'fabric_vendor' => redirect()->route('vendor.dashboard'),
            'subcon_admin' => redirect()->route('subcon.admin.dashboard'),
            'subcon_vendor' => redirect()->route('subcon.vendor.dashboard'),
            'finance_admin' => redirect()->route('finance.admin.dashboard'),
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
            'finance_admin' => redirect()->route('finance.admin.dashboard'),
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
    Route::post('/vendor/{id}/toggle-status', [AdminController::class, 'toggleVendorStatus'])->name('vendor.toggle-status');
    Route::post('/vendor/{id}/reset-password', [AdminController::class, 'resetVendorPassword'])->name('vendor.reset-password');
    Route::post('/vendor/{id}/create-account', [AdminController::class, 'createVendorAccount'])->name('vendor.create-account');
    Route::delete('/vendor/{id}', [AdminController::class, 'deleteVendor'])->name('vendor.destroy');

    // Admin Item Processing Routes (Mirrors Vendor)
    Route::get('/item/{id}/process', [AdminController::class, 'processItem'])->name('item.process');
    Route::post('/item/{id}/save-rolls', [AdminController::class, 'saveItemRolls'])->name('item.save-rolls');
    Route::post('/item/{id}/upload-rolls', [AdminController::class, 'uploadRollsData'])->name('item.upload-rolls');
    Route::get('/item/{id}/rolls-template', [AdminController::class, 'downloadRollsTemplate'])->name('item.rolls-template');
    Route::post('/rolls/{roll}/delete', [AdminController::class, 'deleteRoll'])->name('roll.delete');
    Route::get('/rolls/{roll}/qr', [AdminController::class, 'rollQrCode'])->name('roll.qr');
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

    // Fabric Approvals (tolerance amendment / partial shipment requests)
    Route::get('/approvals', [AdminController::class, 'approvals'])->name('approvals');
    Route::post('/approvals/{request}/approve', [App\Http\Controllers\ApprovalController::class, 'approveInApp'])->name('approvals.approve');
    Route::post('/approvals/{request}/decline', [App\Http\Controllers\ApprovalController::class, 'declineInApp'])->name('approvals.decline');

    // Fabric Workflow (approval email routing)
    Route::get('/workflow', [AdminController::class, 'workflow'])->name('workflow');
    Route::post('/workflow', [AdminController::class, 'updateWorkflow'])->name('workflow.update');
});

// Finance Admin Routes
Route::middleware(['auth'])->prefix('finance/admin')->name('finance.admin.')->group(function () {
    Route::get('/dashboard', [FinanceAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/pending-payment', [FinanceAdminController::class, 'pendingPayment'])->name('pending-payment');
    Route::get('/pending-payment/export', [FinanceAdminController::class, 'pendingPaymentExport'])->name('pending-payment.export');
    Route::get('/fabric-delivery', [FinanceAdminController::class, 'fabricDelivery'])->name('fabric-delivery');
    Route::get('/fabric-delivery/export', [FinanceAdminController::class, 'fabricDeliveryExport'])->name('fabric-delivery.export');
    Route::get('/invoices', [FinanceAdminController::class, 'invoices'])->name('invoices');
    Route::get('/debit-notes', [FinanceAdminController::class, 'debitNotes'])->name('debit-notes');
    Route::post('/debit-notes/retry-waiting', [FinanceAdminController::class, 'retryAllWaitingDebitNotes'])->name('debit-notes.retry-waiting');
    Route::post('/checks/{id}/toggle', [FinanceAdminController::class, 'toggleCheck'])->name('checks.toggle');
    Route::get('/report/{rpaType}/{id}', [FinanceAdminController::class, 'report'])->name('report');
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
    Route::get('/rolls/{roll}/qr', [VendorController::class, 'rollQrCode'])->name('roll.qr');
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
    Route::post('/tolerance/{request}/cancel', [VendorController::class, 'cancelAmendmentRequest'])->name('tolerance.cancel');
    Route::post('/partial/request', [VendorController::class, 'requestPartialShipment'])->name('partial.request');
});

// Approval Routes (Signed URLs)
// Relative signature (path + query only) so it survives the HTTPS reverse proxy.
Route::get('/tolerance/approve/{request}', [App\Http\Controllers\ApprovalController::class, 'approveAmendment'])->name('tolerance.approve')->middleware('signed:relative');
Route::post('/tolerance/approve-submit/{request}', [App\Http\Controllers\ApprovalController::class, 'approveAmendmentSubmit'])->name('tolerance.approve.submit')->middleware('signed:relative');
Route::get('/tolerance/decline/{request}', [App\Http\Controllers\ApprovalController::class, 'declineAmendment'])->name('tolerance.decline')->middleware('signed:relative');
Route::post('/tolerance/decline-submit/{request}', [App\Http\Controllers\ApprovalController::class, 'declineAmendmentSubmit'])->name('tolerance.decline.submit')->middleware('signed:relative');

// Subcon Admin Routes
Route::middleware(['auth'])->prefix('subcon/admin')->name('subcon.admin.')->group(function () {
    Route::get('/dashboard', [SubconAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/invoices', [SubconAdminController::class, 'invoices'])->name('invoices');
    Route::get('/invoices/report/{id}', [SubconAdminController::class, 'invoiceReport'])->name('invoices.report');
    Route::get('/debit-notes', [SubconAdminController::class, 'debitNotes'])->name('debit-notes');
    Route::get('/debit-notes/report/{id}', [SubconAdminController::class, 'debitNoteReport'])->name('debit-notes.report');
    Route::get('/logs', [SubconAdminController::class, 'logs'])->name('logs');
    Route::post('/sync-orders', [SubconAdminController::class, 'syncOrders'])->name('sync-orders');
    Route::get('/sync-status', [SubconAdminController::class, 'syncStatus'])->name('sync-status');
    Route::get('/vendors', [SubconAdminController::class, 'vendors'])->name('vendors');
    Route::post('/vendors', [SubconAdminController::class, 'storeVendor'])->name('vendor.store');
    Route::put('/vendors/{id}', [SubconAdminController::class, 'updateVendor'])->name('vendors.update');
    Route::post('/vendors/{id}/toggle-status', [SubconAdminController::class, 'toggleVendorStatus'])->name('vendors.toggle-status');
    Route::post('/vendors/{id}/reset-password', [SubconAdminController::class, 'resetVendorPassword'])->name('vendors.reset-password');
    Route::post('/vendors/{id}/create-account', [SubconAdminController::class, 'createVendorAccount'])->name('vendors.create-account');
    Route::delete('/vendors/{id}', [SubconAdminController::class, 'deleteVendor'])->name('vendors.destroy');
    Route::get('/approvals', [SubconAdminController::class, 'approvals'])->name('approvals');
    Route::get('/report-validations', [SubconAdminController::class, 'reportValidations'])->name('report-validations');
    Route::get('/director-approvals', [SubconAdminController::class, 'directorApprovals'])->name('director-approvals');
    Route::post('/director-approvals/{token}/recall', [SubconAdminController::class, 'recallToReportValidation'])->name('director-approvals.recall');
    Route::get('/approval-logs', [SubconAdminController::class, 'approvalLogs'])->name('approval-logs');
    Route::get('/orders', [SubconAdminController::class, 'orders'])->name('orders');
    Route::get('/orders-waiting-distribution', [SubconAdminController::class, 'waitingDistribution'])->name('orders-waiting-distribution');
    Route::post('/orders/{id}/generate-labels-manual', [SubconAdminController::class, 'generateLabelsManual'])->name('orders.generate-labels-manual');
    Route::get('/orders/{id}', [SubconAdminController::class, 'viewOrder'])->name('orders.view');
    Route::get('/orders/{id}/export-cutting', [SubconAdminController::class, 'exportCuttingReport'])->name('orders.export-cutting');
    Route::get('/orders/{id}/cutting-plan', [SubconCuttingPlanController::class, 'show'])->name('orders.cutting-plan');
    Route::post('/orders/{id}/cutting-plan', [SubconCuttingPlanController::class, 'save'])->name('orders.cutting-plan.save');
    Route::post('/orders/{id}/status', [SubconAdminController::class, 'updateOrderStatus'])->name('orders.update-status');
    Route::post('/orders/{id}/capacity', [SubconAdminController::class, 'updateCapacity'])->name('orders.update-capacity');
    Route::post('/orders/{id}/approve', [SubconApprovalController::class, 'approveInApp'])->name('orders.approve');
    Route::post('/orders/{id}/decline', [SubconApprovalController::class, 'declineInApp'])->name('orders.decline');
    Route::post('/orders/{id}/material-return', [SubconAdminController::class, 'uploadMaterialReturn'])->name('orders.material-return');
    Route::post('/orders/{id}/material-return/dispatch', [SubconAdminController::class, 'dispatchMaterialReturnTask'])->name('orders.material-return.dispatch');
    Route::get('/orders/{id}/print-labels', [SubconAdminController::class, 'printPackagingLabels'])->name('orders.print-labels');
    Route::get('/workflow', [SubconAdminController::class, 'workflow'])->name('workflow');
    Route::post('/workflow', [SubconAdminController::class, 'updateWorkflow'])->name('workflow.update');
    Route::get('/user-guide', [SubconAdminController::class, 'userGuide'])->name('user-guide');
});

// Subcon Approval Routes (Signed URLs from approval emails)
// Relative signature (path + query only) so it survives the HTTPS reverse proxy
// — the absolute host/scheme the app sees internally differs from the public URL.
Route::get('/subcon/approve/{order}/{gate}', [SubconApprovalController::class, 'approveSigned'])->name('subcon.approve')->middleware('signed:relative');
// No-login cutting-approval form submit (consumption + approve). Signed POST.
Route::post('/subcon/approve-cutting/{order}', [SubconApprovalController::class, 'approveCuttingSubmit'])->name('subcon.approve.cutting.submit')->middleware('signed:relative');
Route::get('/subcon/decline/{order}/{gate}', [SubconApprovalController::class, 'declineSigned'])->name('subcon.decline')->middleware('signed:relative');
// No-login decline-confirm form submit (reason + decline). Signed POST.
Route::post('/subcon/decline-submit/{order}/{gate}', [SubconApprovalController::class, 'declineSubmit'])->name('subcon.decline.submit')->middleware('signed:relative');
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
Route::post('/qc/ho-send/{token}', [App\Http\Controllers\QcApprovalController::class, 'hoSendApproval'])->name('qc.ho-send.submit');
// Material Flow attach/dispatch — available on this same token-gated form
// (Final Approval and Report Validation both render it). POST-only, human
// button-click behind the rendered page — same safety shape as every other
// mutation here (see the GET/POST split note on qc.ho-decline below).
Route::post('/qc/ho-approve/{token}/material-return', [App\Http\Controllers\QcApprovalController::class, 'uploadMaterialReturnSigned'])->name('qc.ho-approve.material-return');
Route::post('/qc/ho-approve/{token}/material-return/dispatch', [App\Http\Controllers\QcApprovalController::class, 'dispatchMaterialReturnTaskSigned'])->name('qc.ho-approve.material-return.dispatch');
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
    Route::post('/orders/{id}/material-reconciliation', [SubconVendorController::class, 'saveMaterialReconciliation'])->name('orders.material-reconciliation');
    Route::post('/orders/{id}/submit-gramasi', [SubconVendorController::class, 'submitGramasi'])->name('orders.submit-gramasi');
    Route::post('/orders/{id}/complete', [SubconVendorController::class, 'completeOrder'])->name('orders.complete');
    Route::get('/orders/{id}/template', [SubconVendorController::class, 'downloadTemplate'])->name('orders.download-template');
    Route::post('/orders/{id}/upload-report', [SubconVendorController::class, 'uploadReport'])->name('orders.upload-report');
    Route::post('/orders/{id}/material-return', [SubconVendorController::class, 'uploadMaterialReturn'])->name('orders.material-return');
    Route::get('/orders/{id}/print-labels', [SubconVendorController::class, 'printPackagingLabels'])->name('orders.print-labels');
    Route::get('/profile', [SubconVendorController::class, 'profile'])->name('profile');
    Route::put('/profile', [SubconVendorController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [SubconVendorController::class, 'updatePassword'])->name('profile.password');
    Route::get('/user-guide', [SubconVendorController::class, 'userGuide'])->name('user-guide');
});

// Notification Routes
Route::post('/notifications/mark-as-read', function () {
    auth()->user()->unreadNotifications->each->markAsRead();

    return response()->json(['success' => true]);
})->name('notifications.mark-as-read')->middleware('auth');
