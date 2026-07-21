<?php

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\RideController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\VendorController;
use function App\Helpers\getCitiesByState;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\UserTypeController;
use function App\Helpers\getStatesByCountry;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExtraServiceController;
use App\Http\Controllers\PaymentReviewController;
use App\Http\Controllers\ExceptionalController;
use App\Http\Controllers\LeadTrackingController;
use App\Http\Controllers\ProductSyncController;
use App\Http\Controllers\VendorPaymentController;
use App\Http\Controllers\ServiceAddressController;
use App\Http\Controllers\UpcomingFollowUpController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\NotificationMasterController;
use App\Http\Controllers\SendMessageController;
use Carbon\Carbon;

use App\Http\Controllers\SalesExecutiveManagementController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', function () {
    return view('admin.auth.login');
});

// HELPER FUNCTION ROUTES
// State/City routes (web-accessible)
Route::get('/states/{countryId}', function ($countryId) {
    try {
        return response()->json([
            'states' => getStatesByCountry($countryId)
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 400);
    }
});

Route::get('/cities/{stateId}', function ($stateId) {
    try {
        return response()->json(getCitiesByState($stateId));
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 400);
    }
});

Route::post('/', [UserController::class, 'login'])->name('login');
Route::post('/forgot-password', [UserController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password/{token}', [UserController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [UserController::class, 'resetPassword'])->name('password.update');
Route::get('/forgot-password', [UserController::class, 'showForgotPasswordForm'])->name('password.request');
Route::get('/download-log', [UserController::class, 'downloadLog'])->name('log.download');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [UserController::class, 'logout'])->name('logout');

    // Sales Dashboard
    Route::get('/sales-dashboard', [DashboardController::class, 'getSalesDashboard'])->middleware('role:ADMIN_ROLES,SALES_ROLES')->name('admin.sales-dashboard');
    Route::get('/sales-dashboard/target-progress', [DashboardController::class, 'getTargetProgressData'])->middleware('role:ADMIN_ROLES,SALES_ROLES')->name('admin.sales-dashboard.target-progress');
    Route::get('/sales-dashboard/product-summary', [DashboardController::class, 'getProductSummaryData'])->middleware('role:ADMIN_ROLES,SALES_ROLES')->name('admin.sales-dashboard.product-summary');
    Route::get('/sales-dashboard/today-followups', [DashboardController::class, 'getTodayFollowUpsData'])->middleware('role:ADMIN_ROLES,SALES_ROLES')->name('admin.sales-dashboard.today-followups');


    Route::prefix('admin/lead')->group(function () {
        Route::get('/', [ClientController::class, 'index'])->middleware('role:ADMIN_ROLES,SALES_ROLES,OPERATIONS_ROLES')->name('admin.clients.index');
        Route::get('/create', [ClientController::class, 'create'])->middleware('role:ADMIN_ROLES,SALES_ROLES,OPERATIONS_ROLES')->name('admin.clients.create');

        Route::post('/', [ClientController::class, 'store'])->name('admin.clients.store');
        Route::get('/{client}/edit', [ClientController::class, 'edit'])->name('admin.clients.edit');
        Route::put('/{client}', [ClientController::class, 'update'])->name('admin.clients.update');
        Route::get('/{client}', [ClientController::class, 'view'])->name('admin.clients.view');
        Route::delete('/{client}', [ClientController::class, 'destroy'])->name('admin.clients.destroy');
        Route::patch('/toggle-status/{client}', [ClientController::class, 'toggleStatus'])->name('admin.clients.toggle-status');
        Route::get('/{client}/follow-up/create', [ClientController::class, 'createFollowUp'])->name('admin.clients.follow-up.create');
        Route::post('/{client}/follow-up', [ClientController::class, 'storeFollowUp'])->name('admin.clients.follow-up.store');
        Route::post('/{client}/generate-passenger-registration-link', [ClientController::class, 'generatePassengerRegistrationLink'])->name('admin.clients.generate-passenger-registration-link');
        Route::get('/{client}/get-passenger-registration-link', [ClientController::class, 'getPassengerRegistrationLink'])->name('admin.clients.get-passenger-registration-link');
        // Route::post('/get-extra-services-by-services', [ClientController::class, 'getExtraServicesByServices']);
    });
    Route::get('/fetch-services/{productId}', [ClientController::class, 'fetchServices'])->name('admin.clients.fetch-services');

    // Lead Tracking Routes
    Route::prefix('admin/lead-tracking')->group(function () {
        Route::get('/', [LeadTrackingController::class, 'index'])
            ->middleware('role:ADMIN_ROLES,ACCOUNTS_ROLES')
            ->name('admin.lead-tracking.index');
        Route::get('/{id}', [LeadTrackingController::class, 'show'])
            ->middleware('role:ADMIN_ROLES,ACCOUNTS_ROLES')
            ->name('admin.lead-tracking.show');
    });

    // Separate route group for lead-specific operations
    Route::prefix('admin/leads')->group(function () {
        // Static routes first so segments like "import" are not captured by the dynamic {lead} binding
        Route::get('/import', [ClientController::class, 'showImportForm'])->name('admin.leads.import');
        Route::post('/import', [ClientController::class, 'importLeads'])->name('admin.leads.import.store');
        Route::get('/import/sample', [ClientController::class, 'downloadSampleExcel'])->name('admin.leads.import.sample');
        Route::get('/export', [ClientController::class, 'exportLeads'])->name('admin.leads.export');

        // Dynamic routes that expect a UUID lead identifier
        Route::get('/{lead}', [ClientController::class, 'viewLead'])
            ->whereUuid('lead')
            ->name('admin.leads.view');

        Route::get('/{lead}/edit', [ClientController::class, 'editLead'])
            ->whereUuid('lead')
            ->name('admin.leads.edit');

        Route::put('/{lead}', [ClientController::class, 'updateLead'])
            ->whereUuid('lead')
            ->name('admin.leads.update');

        Route::delete('/{lead}', [ClientController::class, 'destroyLead'])
            ->whereUuid('lead')
            ->name('admin.leads.destroy');

        Route::get('/{lead}/follow-up/create', [ClientController::class, 'createLeadFollowUp'])
            ->whereUuid('lead')
            ->name('admin.leads.follow-up.create');

        Route::post('/{lead}/follow-up', [ClientController::class, 'storeLeadFollowUp'])
            ->whereUuid('lead')
            ->name('admin.leads.follow-up.store');
    });

    // Airpoints integration routes
    Route::post('admin/airpoints/check-user-points', [ClientController::class, 'checkUserPoints'])
        ->name('admin.airpoints.check-user-points');

    Route::get('admin/dnp-leads', [ClientController::class, 'getDnpLeads'])->middleware('role:ADMIN_ROLES,SALES_ROLES,OPERATIONS_ROLES')->name('admin.leads.dnp');
    Route::get('admin/dnp-leads/export', [ClientController::class, 'exportDnpLeads'])->middleware('role:ADMIN_ROLES,SALES_ROLES,OPERATIONS_ROLES')->name('admin.leads.dnp.export');

    // Separate follow-ups group
    Route::prefix('admin/followups')->group(function () {
        Route::put('{followup}/update-image', [ClientController::class, 'updateImage'])
            ->name('admin.followups.update-image');
        // Allow super-admin to delete payment-related followups (partial/full) via a dedicated endpoint
        Route::delete('{followup}', [ClientController::class, 'destroyFollowup'])
            ->name('admin.followups.destroy');
    });
    // ===============================
    // Vendor Routes
    // ===============================
    Route::prefix('admin/vendors')->middleware('role:ADMIN_ROLES,ACCOUNTS_ROLES,OPERATIONS_ROLES')->group(function () {
        Route::get('/', [VendorController::class, 'index'])->name('admin.vendors.index');
        Route::get('/create', [VendorController::class, 'create'])->name('admin.vendors.create');
        Route::post('/', [VendorController::class, 'store'])->name('admin.vendors.store');
        Route::get('/get-service-products', [VendorController::class, 'getServiceProducts'])
            ->name('admin.vendors.get-service-products');
        // Use whereUuid() to force UUID format in these bindings
        Route::get('/{vendor}/edit', [VendorController::class, 'edit'])
            ->name('admin.vendors.edit');

        Route::put('/{vendor}', [VendorController::class, 'update'])
            ->name('admin.vendors.update');

        Route::get('/{vendor}', [VendorController::class, 'view'])
            ->name('admin.vendors.view');

        Route::get('/{vendor}/view-modal', [VendorController::class, 'viewModal'])
            ->name('admin.vendors.view-modal');

        Route::delete('/{vendor}', [VendorController::class, 'destroy'])
            ->name('admin.vendors.destroy');

        Route::patch('/toggle-status/{vendor}', [VendorController::class, 'toggleStatus'])
            ->name('admin.vendors.toggle-status');

        // AJAX routes for location dropdowns
        Route::get('/states/{countryId}', [VendorController::class, 'getStatesByCountry'])
            ->whereUuid('countryId')
            ->name('admin.vendors.getStatesByCountry');

        Route::get('/cities/{stateId}', [VendorController::class, 'getCitiesByState'])
            ->whereUuid('stateId')
            ->name('admin.vendors.getCitiesByState');
    });

    Route::get('/get-cities/{countryId}', [ClientController::class, 'getCitiesByCountry']);

    Route::post('/change-password', [UserController::class, 'updatePassword'])->name('user.password.update');
    Route::get('change-password', [UserController::class, 'showChangePasswordForm'])->name('password.change');


    Route::get('/payment-detail', function () {
        return view('admin.pages.payments.payment-details');
    });

    Route::get('/leads-payment-detail', function () {
        return view('admin.pages.accounts.leads-payment-details');
    });

    Route::get('/booking-detail', function () {
        return view('admin.pages.bookings.booking-details');
    });
    Route::get('/generate-invoice', function () {
        return view('admin.pages.bookings.booking-invoice-pdf');
    });

    Route::get('/voucher', function () {
        return view('admin.pages.voucher.voucher');
    });

    // Payment Review Routes
    Route::prefix('admin/account')->group(function () {
        // Exceptional Dashboard
        Route::get('/exceptional', [ExceptionalController::class, 'index'])->middleware('role:ADMIN_ROLES,ACCOUNTS_ROLES,OPERATIONS_ROLES')->name('admin.account.exceptional');
        Route::get('/exceptional/{followupId}/edit-total', [ExceptionalController::class, 'editTotal'])->middleware('role:ADMIN_ROLES,ACCOUNTS_ROLES,OPERATIONS_ROLES')->name('admin.account.exceptional.edit-total');
        Route::post('/exceptional/{followupId}/update-total', [ExceptionalController::class, 'updateTotal'])->middleware('role:ADMIN_ROLES,ACCOUNTS_ROLES,OPERATIONS_ROLES')->name('admin.account.exceptional.update-total');
        Route::post('/exceptional/create-refund-note', [ExceptionalController::class, 'createRefundNote'])->middleware('role:ADMIN_ROLES,ACCOUNTS_ROLES,OPERATIONS_ROLES')->name('admin.account.exceptional.create-refund-note');
        Route::post('/exceptional/{followupId}/add-to-sales', [ExceptionalController::class, 'addToSales'])->middleware('role:ADMIN_ROLES,ACCOUNTS_ROLES,OPERATIONS_ROLES')->name('admin.account.exceptional.add-to-sales');
        Route::get('/payment-review', [PaymentReviewController::class, 'index'])->middleware('role:ADMIN_ROLES,ACCOUNTS_ROLES,OPERATIONS_ROLES')->name('admin.account.payment-review');
        Route::get('/payment-review/{id}', [PaymentReviewController::class, 'show'])->name('admin.account.payment-review.show');
        Route::post('/payment-review/{id}/approve', [PaymentReviewController::class, 'approve'])->name('admin.account.payment-review.approve');
        Route::post('/payment-review/{id}/reject', [PaymentReviewController::class, 'reject'])->name('admin.account.payment-review.reject');
        Route::post('/payment-review/{id}/approve-all', [PaymentReviewController::class, 'approveAll'])->name('admin.account.payment-review.approve-all');
        Route::post('/payment-review/{id}/reject-all', [PaymentReviewController::class, 'rejectAll'])->name('admin.account.payment-review.reject-all');
        Route::post('/payment-history/{id}/approve', [PaymentReviewController::class, 'approveHistory'])->name('admin.account.payment-history.approve');
        Route::post('/payment-history/{id}/reject', [PaymentReviewController::class, 'rejectHistory'])->name('admin.account.payment-history.reject');
        Route::get('/payment-review-export', [PaymentReviewController::class, 'export'])->name('admin.account.payment-review.export');

        // Vendor Payments Routes
        Route::get('/vendor-payments', [VendorPaymentController::class, 'index'])->middleware('role:ADMIN_ROLES,OPERATIONS_ROLES,ACCOUNTS_ROLES')->name('admin.account.vendor-payments');
        Route::get('/vendor-payments/export', [VendorPaymentController::class, 'export'])->name('admin.account.vendor-payments.export');
        Route::get('/vendor-payments/{id}', [VendorPaymentController::class, 'show'])->name('admin.account.vendor-payments.show');
        Route::post('/vendor-payments', [VendorPaymentController::class, 'storePayment'])->name('admin.account.vendor-payments.store');
        Route::put('/vendor-payments/{id}/amount', [VendorPaymentController::class, 'updatePaymentAmount'])->name('admin.account.vendor-payments.update-amount');

        // Invoice Routes
        Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('role:ADMIN_ROLES,ACCOUNTS_ROLES,OPERATIONS_ROLES')->name('admin.account.invoices');
        Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->name('admin.account.invoices.show');
        Route::post('/invoices/{id}/generate', [InvoiceController::class, 'generateInvoice'])->name('admin.account.invoices.generate');
        Route::put('/invoices/{id}/gst-info', [InvoiceController::class, 'updateGstInfo'])->name('admin.account.invoices.update-gst-info');
        Route::post('/invoices/{id}/finalize', [InvoiceController::class, 'finalizeInvoice'])->name('admin.account.invoices.finalize');
        Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'generatePDF'])->name('admin.account.invoices.pdf');
        Route::get('/invoices/{id}/download', [InvoiceController::class, 'downloadPDF'])->name('admin.account.invoices.download');
        Route::get('/invoices/{id}/preview', [InvoiceController::class, 'previewHTML'])->name('admin.account.invoices.preview');
    });

    Route::get('/private-jet-pdf', function () {
        return view('admin.pages.pdf.private-jet-pdf');
    });

    Route::prefix('admin/services')->group(function () {
        Route::get('/', [ServiceController::class, 'index'])->name('admin.services.index');
        Route::get('/create', [ServiceController::class, 'create'])->name('admin.services.create');
        Route::post('/', [ServiceController::class, 'store'])->name('admin.services.store');
        Route::get('/{service}/edit', [ServiceController::class, 'edit'])->name('admin.services.edit');
        Route::put('/{service}', [ServiceController::class, 'update'])->name('admin.services.update');
        Route::get('/{service}', [ServiceController::class, 'view'])->name('admin.services.view');
        Route::delete('/{service}', [ServiceController::class, 'destroy'])->name('admin.services.destroy');
        Route::patch('/toggle-status/{service}', [ServiceController::class, 'toggleStatus'])->name('services.toggle-status');
    });

    // ===============================
    // Admin Extra Services Routes
    // ===============================
    Route::prefix('admin/extra-services')->group(function () {
        Route::get('/', [ExtraServiceController::class, 'index'])->name('admin.extra-services.index');
        Route::get('/create', [ExtraServiceController::class, 'create'])->name('admin.extra-services.create');
        Route::post('/', [ExtraServiceController::class, 'store'])->name('admin.extra-services.store');
        Route::get('/{extraService}/edit', [ExtraServiceController::class, 'edit'])->name('admin.extra-services.edit');
        Route::put('/{extraService}', [ExtraServiceController::class, 'update'])->name('admin.extra-services.update');
        Route::get('/{extraService}', [ExtraServiceController::class, 'view'])->name('admin.extra-services.view');
        Route::delete('/{extraService}', [ExtraServiceController::class, 'destroy'])->name('admin.extra-services.destroy');
        Route::patch('/toggle-status/{extraService}', [ExtraServiceController::class, 'toggleStatus'])->name('extra-services.toggle-status');
        Route::get('/{id}/view-modal', [ExtraServiceController::class, 'viewModal']);
    });

    Route::prefix('admin/client')->group(function () {
        Route::get('/', [ClientController::class, 'indexClient'])->middleware('role:ADMIN_ROLES,SALES_ROLES')->name('admin.client.index');
        Route::get('/create', [ClientController::class, 'createClient'])->name('admin.client.create');
        Route::post('/', [ClientController::class, 'storeClient'])->name('admin.client.store');
        Route::get('/{client}/edit', [ClientController::class, 'editClient'])->name('admin.client.edit');
        // Route::post('/{client}', [ClientController::class, 'updateClient'])->name('admin.client.update');
        Route::patch('/{client}', [ClientController::class, 'updateClient'])->name('admin.client.update');
        Route::get('/{client}', [ClientController::class, 'viewClient'])->name('admin.client.view');
        Route::delete('/{client}', [ClientController::class, 'destroy'])->name('admin.client.destroy');
        Route::get('/{client}/data', [ClientController::class, 'getClientData'])->name('admin.client.data');
    });


    // ===============================
    // Admin User Roles Routes
    // ===============================
    Route::prefix('admin/user-types')->group(function () {
        Route::get('/', [UserTypeController::class, 'create'])->name('admin.user-types.create');
        Route::post('/', [UserTypeController::class, 'store'])->name('admin.user-types.store');
        Route::get('/edit/{id}', [UserTypeController::class, 'edit'])->name('admin.user-types.edit');
        Route::put('/update/{id}', [UserTypeController::class, 'update'])->name('admin.user-types.update');
        Route::post('/{id}/toggle-status', [UserTypeController::class, 'toggleStatus'])->name('admin.user-types.toggle-status');
    });


    // ===============================
    // Admin Follow-Up Status Routes
    // ===============================
    Route::prefix('admin/upcoming-follow-up')->group(function () {
        Route::get('/', [UpcomingFollowUpController::class, 'index'])->middleware('role:ADMIN_ROLES,SALES_ROLES')->name('admin.upcoming-follow-up.index');
        Route::post('/store', [UpcomingFollowUpController::class, 'store'])->name('admin.upcoming-follow-up.store');
        Route::get('/{id}/edit', [UpcomingFollowUpController::class, 'edit'])->name('admin.upcoming-follow-up.edit');
        Route::post('/{id}/update', [UpcomingFollowUpController::class, 'update'])->name('admin.upcoming-follow-up.update');
        Route::post('/toggle', [UpcomingFollowUpController::class, 'toggleStatus'])->name('admin.upcoming-follow-up.toggle');
    });

    Route::prefix('admin/upcoming-follow-up')->group(function () {
        Route::get('/', [UpcomingFollowUpController::class, 'index'])->middleware('role:ADMIN_ROLES,SALES_ROLES')->name('admin.upcoming-follow-up.index');
        Route::post('/store', [UpcomingFollowUpController::class, 'store'])->name('admin.upcoming-follow-up.store');
        Route::get('/{id}/edit', [UpcomingFollowUpController::class, 'edit'])->name('admin.upcoming-follow-up.edit');
        Route::post('/{id}/update', [UpcomingFollowUpController::class, 'update'])->name('admin.upcoming-follow-up.update');
        Route::post('/toggle', [UpcomingFollowUpController::class, 'toggleStatus'])->name('admin.upcoming-follow-up.toggle');
    });


    Route::prefix('admin/users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/create', [UserController::class, 'create'])->name('admin.users.create');
        Route::post('/', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/get-user-for-edit', [UserController::class, 'getUserforEdit'])->name('admin.users.getUserforEdit');
        Route::get('/active', [UserController::class, 'getActiveUsers'])->name('admin.users.active');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::get('/{user}', [UserController::class, 'show'])->name('admin.users.view');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
        Route::get('/user-types/hierarchy', [UserController::class, 'getUserTypeHierarchy'])->name('admin.users.user-types.hierarchy');
        Route::get('/user-types/by-parent', [UserController::class, 'getUserTypesByParent'])->name('admin.users.user-types.by-parent');
        Route::get('/user-types/hierarchy-path', [UserController::class, 'getUserTypeHierarchyPath'])->name('admin.users.user-types.hierarchy-path');
        Route::patch('/toggle-status/{user}', [UserController::class, 'toggleStatus'])->name('admin.users.toggle-status');
    });

    // Target Master Routes
    Route::prefix('admin/targets')->group(function () {
        Route::get('/', [TargetController::class, 'index'])->name('admin.targets.index');
        Route::get('/create', [TargetController::class, 'create'])->name('admin.targets.create');
        Route::post('/', [TargetController::class, 'store'])->name('admin.targets.store');

        // AJAX Routes - Must be before parameterized routes
        Route::get('/data/table', [TargetController::class, 'getTargetsData'])->name('admin.targets.data');
        Route::get('/data/sales-executives', [TargetController::class, 'getSalesExecutivesData'])->name('admin.targets.sales-executives');

        Route::get('/{target}', [TargetController::class, 'show'])->name('admin.targets.show');
        Route::get('/{target}/edit', [TargetController::class, 'edit'])->name('admin.targets.edit');
        Route::put('/{target}', [TargetController::class, 'update'])->name('admin.targets.update');
        Route::delete('/{target}', [TargetController::class, 'destroy'])->name('admin.targets.destroy');
    });

    //notification master routes
    Route::prefix('admin')->name('admin.')->group(function () {

        Route::get('/notification-master', [NotificationMasterController::class, 'index'])
            ->name('notification-master.index');

        Route::get('/notification-master/create', [NotificationMasterController::class, 'create'])
            ->name('notification-master.create');

        Route::post('/notification-master', [NotificationMasterController::class, 'store'])
            ->name('notification-master.store');

        Route::get('/notification-master/{id}/edit', [NotificationMasterController::class, 'edit'])
            ->name('notification-master.edit');

        Route::put('/notification-master/{id}', [NotificationMasterController::class, 'update'])
            ->name('notification-master.update');

        Route::delete('/notification-master/{id}', [NotificationMasterController::class, 'destroy'])
            ->name('notification-master.destroy');
    });

    // Sales Executive Management Routes
    Route::prefix('admin/sales-executive-management')->middleware('role:ADMIN_ROLES,SALES_ROLES')->group(function () {
        Route::get('/', [\App\Http\Controllers\SalesExecutiveManagementController::class, 'index'])->name('admin.sales-executive-management.index');
        Route::post('/', [\App\Http\Controllers\SalesExecutiveManagementController::class, 'store'])->name('admin.sales-executive-management.store');
        Route::delete('/{id}', [\App\Http\Controllers\SalesExecutiveManagementController::class, 'destroy'])->name('admin.sales-executive-management.destroy');
        Route::get('/api/assigned-executives', [\App\Http\Controllers\SalesExecutiveManagementController::class, 'getAssignedSalesExecutives'])->name('admin.sales-executive-management.assigned-executives');
    });

    Route::prefix('admin/products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('admin.products.index');
        Route::get('/create', [ProductController::class, 'create'])->name('admin.products.create');
        Route::post('/', [ProductController::class, 'store'])->name('admin.products.store');
        Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('admin.products.edit');
        Route::put('/{product}', [ProductController::class, 'update'])->name('admin.products.update');
        Route::get('/{product}', [ProductController::class, 'show'])->name('admin.products.view');
        Route::patch('/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('admin.products.toggle-status');
        Route::post('/users-by-products', [ProductController::class, 'getUsersByProducts'])->name('admin.products.users-by-products');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('admin.products.destroy');
    });

    // Product Sync to Airpoints
    Route::prefix('admin/product-sync')->group(function () {
        Route::get('/', [ProductSyncController::class, 'index'])->name('admin.product-sync.index');
        Route::post('/sync', [ProductSyncController::class, 'syncProducts'])->name('admin.product-sync.sync');
    });

    Route::prefix('admin/service-addresses')->group(function () {
        Route::get('/', [ServiceAddressController::class, 'index'])->name('admin.service-addresses.index');
        Route::get('/create', [ServiceAddressController::class, 'create'])->name('admin.service-addresses.create');
        Route::post('/', [ServiceAddressController::class, 'store'])->name('admin.service-addresses.store');
        Route::get('/{id}/edit', [ServiceAddressController::class, 'edit'])->name('admin.service-addresses.edit');
        Route::put('/{id}', [ServiceAddressController::class, 'update'])->name('admin.service-addresses.update');
        Route::get('/{address}', [ServiceAddressController::class, 'show'])->name('admin.service-addresses.view');
        Route::patch('/{address}/toggle-status', [ServiceAddressController::class, 'toggleStatus'])->name('admin.service-addresses.toggle-status');
        Route::get('/get-services-by-product/{productId}', [ServiceAddressController::class, 'getServicesByProduct']);
        Route::get('/states/{countryId}', [ServiceAddressController::class, 'getStatesByCountry']);
        Route::get('/cities/{stateId}', [ServiceAddressController::class, 'getCitiesByState']);
        Route::get('/{id}/view-modal', [ServiceAddressController::class, 'viewModal']);
        Route::get('/{id}/edit-details', [ServiceAddressController::class, 'getEditDetails'])
            ->name('admin.service-addresses.edit-details');
    });

    Route::prefix('admin/vouchers')->group(function () {
        Route::get('/', [VoucherController::class, 'index'])->middleware('role:ADMIN_ROLES,OPERATIONS_ROLES')->name('admin.vouchers.index');
        Route::get('/generate/{lead_id}', [VoucherController::class, 'showVoucherForm'])->name('admin.vouchers.generate');
        Route::get('/form/{lead_id}', [VoucherController::class, 'showVoucherForm'])->name('admin.vouchers.form');
        Route::post('/store', [VoucherController::class, 'storeVoucher'])->name('admin.vouchers.store');
        Route::post('/get-service-addresses', [VoucherController::class, 'getServiceAddresses'])->name('admin.vouchers.getServiceAddresses');
        Route::get('/{voucher_id}/pdf', [VoucherController::class, 'generateVoucherPdf'])->name('admin.vouchers.pdf');
        Route::post('/{voucher_id}/send', [VoucherController::class, 'sendVoucherPdf'])->name('admin.vouchers.send');
        // Send voucher via WhatsApp (generate PDF and send via WhatsApp)
        Route::post('/{voucher_id}/send-whatsapp', [VoucherController::class, 'sendVoucherWhatsApp'])->name('admin.vouchers.send-whatsapp');
        // Resend registration link to client email
        Route::post('/{voucher_id}/resend-registration-link', [VoucherController::class, 'resendRegistrationLink'])->name('admin.vouchers.resend-registration-link');
        // Send registration link via WhatsApp only
        Route::post('/{voucher_id}/send-registration-whatsapp', [VoucherController::class, 'sendRegistrationLinkWhatsApp'])->name('admin.vouchers.send-registration-whatsapp');
        // Delete extra attachment (file) associated with a voucher
        Route::delete('/{voucher_id}/attachment', [VoucherController::class, 'deleteAttachment'])->name('admin.vouchers.delete-attachment');
    });

    Route::prefix('admin/rides')->group(function () {
        Route::get('/upcoming-ride', [RideController::class, 'upcomingRides'])->middleware('role:ADMIN_ROLES,OPERATIONS_ROLES,SALES_ROLES')->name('admin.rides.upcoming');
        Route::get('/api/calendar-events', [RideController::class, 'getCalendarEvents'])->name('admin.rides.calendar.events');
        Route::get('/api/ride-details/{rideId}', [RideController::class, 'getRideDetails'])->name('admin.rides.details');
        Route::get('/debug/test-data', [RideController::class, 'debugTestData'])->name('admin.rides.debug');

        // Ride Status Routes
        Route::get('/ride-status', [RideController::class, 'rideStatus'])->middleware('role:ADMIN_ROLES,OPERATIONS_ROLES,ACCOUNTS_ROLES')->name('admin.rides.ride-status');
        Route::get('/ride-status/export', [RideController::class, 'exportRideStatus'])->middleware('role:ADMIN_ROLES,OPERATIONS_ROLES,ACCOUNTS_ROLES')->name('admin.rides.ride-status.export');
        Route::get('/ride-status/{rideId}/details', [RideController::class, 'getRideStatusDetails'])->name('admin.rides.ride-status.details');
        Route::post('/ride-status/{rideId}/update-status', [RideController::class, 'updateRideStatus'])->name('admin.rides.ride-status.update-status');
        Route::post('/ride-status/{rideId}/test-status', [RideController::class, 'testStatusUpdate'])->name('admin.rides.ride-status.test-status');
        Route::post('/ride-status/{rideId}/update-dates', [RideController::class, 'updateRideDates'])->name('admin.rides.ride-status.update-dates');
        Route::post('/ride-status/{rideId}/generate-invoice', [RideController::class, 'generateInvoice'])->name('admin.rides.ride-status.generate-invoice');
        Route::post('/ride-status/{rideId}/generate-refund', [RideController::class, 'generateRefundNote'])->name('admin.rides.ride-status.generate-refund');
        Route::post('/ride-status/{rideId}/save-refund', [RideController::class, 'saveRefundFromRideStatus'])->middleware('role:ADMIN_ROLES,ACCOUNTS_ROLES,OPERATIONS_ROLES')->name('admin.rides.ride-status.save-refund');
        Route::post('/rides/{rideId}/send-refund-email', [RideController::class, 'sendRefundEmail'])->name('admin.rides.ride-status.send-refund-email');
    });

    Route::prefix('admin/refunds')->group(function () {
        Route::get('/', [RefundController::class, 'index'])->middleware('role:ADMIN_ROLES,ACCOUNTS_ROLES,OPERATIONS_ROLES')->name('admin.refunds.index');
        Route::get('/{followupId}/details', [RefundController::class, 'show'])->name('admin.refunds.show');
        Route::post('/store', [RefundController::class, 'store'])->name('admin.refunds.store');
        Route::get('/{refundId}/download', [RefundController::class, 'download'])->name('admin.refunds.download');
        Route::delete('/{refundId}/proof', [RefundController::class, 'deleteProof'])->name('admin.refunds.delete-proof');
        Route::post('/refunds/{refundId}/mark-done', [RefundController::class, 'markAsDone'])->name('admin.refunds.mark-done');
        Route::get('/{refundId}/invoice/pdf', [RefundController::class, 'generateRefundInvoice'])->name('admin.refunds.invoice.pdf');
        Route::get('/{refundId}/invoice/download', [RefundController::class, 'downloadRefundInvoice'])->name('admin.refunds.invoice.download');
        Route::get('/{refundId}/invoice/preview', [RefundController::class, 'previewRefundInvoice'])->name('admin.refunds.invoice.preview');
    });

    Route::prefix('admin/report')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('admin.report');
        Route::get('/sales', [ReportController::class, 'salesReport'])->name('admin.report.sales');
        Route::get('/sales/sales-persons-by-manager', [ReportController::class, 'getSalesPersonsByManager'])->name('admin.report.sales.persons-by-manager');
        Route::get('/sales/export', [ReportController::class, 'exportSalesReport'])->name('admin.report.sales.export');
        // Vendor report for account side (flat per-vendor view)
        Route::get('/vendor-payments', [ReportController::class, 'vendorReport'])->name('admin.report.vendor');
        Route::get('/vendor-payments/export', [ReportController::class, 'exportVendorReport'])->name('admin.report.vendor.export');
        // Profit/Loss report for account side
        Route::get('/profit-loss', [ReportController::class, 'profitLossReport'])->name('admin.report.profit-loss');
        Route::get('/profit-loss/export', [ReportController::class, 'exportProfitLossReport'])->name('admin.report.profit-loss.export');
        Route::get('/kpi', [ReportController::class, 'kpiReport'])->name('admin.report.kpi');
        Route::get('/kpi/export', [ReportController::class, 'exportKpiReport'])->name('admin.report.kpi.export');
        Route::get('/kpi/export-individual/{representative_id}', [ReportController::class, 'exportKpiReportIndividual'])->name('admin.report.kpi.export.individual');
    });
});
//Route::get('/home', [UserController::class, 'applyUuid'])->name('home')->middleware('auth');

Route::get('/clear-all', function () {
    Artisan::call('route:cache');
    Artisan::call('config:clear');
    Artisan::call('config:cache');
    Artisan::call('view:clear');
    return "Cleared All !";
});

// Public registration routes - constrain voucher to UUID so static segments like 'thanks' are not treated as voucher id
Route::get('/voucher/register/thanks', [\App\Http\Controllers\RegistrationController::class, 'thanks'])->name('voucher.register.thanks');
Route::get('/voucher/register/{voucher}/{token?}', [\App\Http\Controllers\RegistrationController::class, 'showForm'])
    ->whereUuid('voucher')
    ->name('voucher.register.form');
Route::post('/voucher/register/{voucher}', [\App\Http\Controllers\RegistrationController::class, 'store'])
    ->whereUuid('voucher')
    ->name('voucher.register.store');

// Pre-voucher registration routes (for collecting passenger details before voucher creation)
Route::get('/lead/register/thanks', [\App\Http\Controllers\PreVoucherRegistrationController::class, 'thanks'])->name('lead.register.thanks');
Route::get('/r/{slug}', [\App\Http\Controllers\PreVoucherRegistrationController::class, 'redirectBySlug'])
    ->where('slug', '[A-Za-z0-9_-]{4,64}')
    ->name('lead.register.short');
Route::get('/lead/register/{lead}/{token?}', [\App\Http\Controllers\PreVoucherRegistrationController::class, 'showForm'])
    ->whereUuid('lead')
    ->name('lead.register.form');
Route::post('/lead/register/{lead}', [\App\Http\Controllers\PreVoucherRegistrationController::class, 'store'])
    ->whereUuid('lead')
    ->name('lead.register.store');

//  Route::get('/test-msg', [\App\Http\Controllers\VoucherController::class, 'sendTestMessage'])->name('test.msg');