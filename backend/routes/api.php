<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AgeUnitController;
use App\Http\Controllers\AnswerTypeController;
use App\Http\Controllers\AntibioticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContractsController;
use App\Http\Controllers\CultureController;
use App\Http\Controllers\DiscountTypeController;
use App\Http\Controllers\DurationUnitController;
use App\Http\Controllers\FrontendLogController;
use App\Http\Controllers\GenderController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PatientPortalController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\NationalityController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PatientQuestionController;
use App\Http\Controllers\PatientsController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\ReferalController;
use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ResultStatusController;
use App\Http\Controllers\ResultTypeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SampleController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\TestGroupController;
use App\Http\Controllers\TitleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\LabDeviceController;
use App\Http\Controllers\DeviceResultController;
use App\Http\Controllers\LabSettingController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * User Auth Api Routes - With Rate Limiting
 */
Route::post('user/register', [AuthController::class, 'register'])
    ->middleware('throttle:register');

Route::post('user/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('user/resend_code', [AuthController::class, 'resendCode'])
    ->middleware('throttle:resend-code');

Route::post('user/verify_code', [AuthController::class, 'verifyCode'])
    ->middleware('throttle:verification');

Route::post('user/forget_password', [AuthController::class, 'forgetPassword'])
    ->middleware('throttle:password-reset');

Route::post('user/reset_password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:password-reset');

Route::get('lab-background/{labId}', [UserController::class, 'getBackground']);
Route::get('lab-margins/{labId}', [UserController::class, 'getMargins']);
Route::get('lab-settings/{labId}', [LabSettingController::class, 'showPublic']);
Route::get('invoices/public/{id}', [InvoiceController::class, 'publicShow']);

// Patient portal (magic link) - public, token-secured, no login required.
Route::get('portal/{token}', [PatientPortalController::class, 'show']);
Route::post('portal/{token}/otp/request', [PatientPortalController::class, 'requestOtp'])->middleware('throttle:5,1');
Route::post('portal/{token}/otp/verify', [PatientPortalController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::post('portal/{token}/redeem', [PatientPortalController::class, 'redeem']);

/**
 @ Result status Routes
 */
Route::get('result-status', [ResultStatusController::class, 'index']);

/**
 @ Frontend Log Routes
 */
Route::post('log/error', [FrontendLogController::class, 'error'])->middleware('throttle:30,1');
Route::post('log/warning', [FrontendLogController::class, 'warning'])->middleware('throttle:30,1');
Route::post('log/info', [FrontendLogController::class, 'info'])->middleware('throttle:30,1');
Route::post('log/debug', [FrontendLogController::class, 'debug'])->middleware('throttle:30,1');

/**
 @ Template Downloads (public - no auth needed)
 */
Route::get('tests/download-template', [TestController::class, 'downloadTemplate']);
Route::get('test_groups/download-template', [TestGroupController::class, 'downloadTemplate']);
Route::get('categories/download-template', [CategoryController::class, 'downloadTemplate']);
Route::get('samples/download-template', [SampleController::class, 'downloadTemplate']);
Route::get('packages/download-template', [PackageController::class, 'downloadTemplate']);
Route::get('cultures/download-template', [CultureController::class, 'downloadTemplate']);
Route::get('tests-questions/download-template', [PatientQuestionController::class, 'downloadTemplate']);
Route::get('antibiotics/download-template', [AntibioticsController::class, 'downloadTemplate']);

// Device Agent Routes (api_token auth via X-Device-Token header)
Route::post('device/results', [DeviceResultController::class, 'receiveResults'])
    ->middleware('throttle:60,1');
Route::post('device/heartbeat', [DeviceResultController::class, 'heartbeat'])
    ->middleware('throttle:120,1');

Route::group(['middleware' => 'auth:sanctum'], function (): void {

    /**
     @ User Api Routes
     */
    Route::post('user/create', [AuthController::class, 'store']); // store new user
    Route::post('user/logout', [AuthController::class, 'logoutUser']); // logout user
    Route::get('user/show', [UserController::class, 'index']); // get all users
    Route::get('user/get_user_by_id', [UserController::class, 'getUserById']); // get user by id
    Route::post('user/update', [UserController::class, 'update']); // update user
    Route::delete('user/delete', [UserController::class, 'deleteUser']); // delete user
    Route::post('lab-background', [UserController::class, 'uploadBackground']); // upload report background
    Route::post('lab-margins', [UserController::class, 'saveMargins']); // save print margins

    /**
     @ Activity Routes
     */
    Route::get('activity/show', [ActivityLogController::class, 'index']); // get all activity

    /**
     @ Roles Routes
     */
    Route::get('roles/show', [RolesController::class, 'index']); // get all roles

    /**
     @ Roles Routes
     */
    Route::post('whatsapp/message', [WhatsAppController::class, 'sendWhatsAppMessage']); // send whatsapp message
    Route::post('portal/generate', [PatientPortalController::class, 'generateLink']); // create/reuse a patient's magic-link portal URL

    // Promo codes
    Route::get('promo-codes', [PromoCodeController::class, 'index']);
    Route::post('promo-codes', [PromoCodeController::class, 'store']);
    Route::post('promo-codes/generate-batch', [PromoCodeController::class, 'generateBatch']);
    Route::post('promo-codes/preview', [PromoCodeController::class, 'preview']);
    Route::put('promo-codes/{id}', [PromoCodeController::class, 'update']);
    Route::delete('promo-codes/{id}', [PromoCodeController::class, 'destroy']);
    Route::post('invoices/{invoiceId}/apply-promo-code', [PromoCodeController::class, 'applyToInvoice']);
    Route::delete('invoices/{invoiceId}/promo-code', [PromoCodeController::class, 'removeFromInvoice']);

    /**
     @ Patients Routes
     */
    Route::post('patients/search-name', [InvoiceController::class, 'searchByName']);
    Route::get('patients/{id?}', [PatientsController::class, 'index']);
    Route::get('patients/show', [PatientsController::class, 'show']);
    Route::post('patients/create', [PatientsController::class, 'store']);
    Route::post('patients/update', [PatientsController::class, 'update']);
    Route::delete('patients/delete', [PatientsController::class, 'destroy']);

    /**
     @ Contracts Routes
     */
    Route::get('contracts/{id?}', [ContractsController::class, 'index']);
    Route::get('contracts/show', [ContractsController::class, 'show']);
    Route::post('contracts/create', [ContractsController::class, 'store']);
    Route::put('contracts/update', [ContractsController::class, 'update']);
    Route::delete('contracts/delete', [ContractsController::class, 'destroy']);

    /**
     @ Titles Routes
     */
    Route::get('titles', [TitleController::class, 'index']);
    Route::get('titles/show', [TitleController::class, 'show']);
    Route::post('titles/create', [TitleController::class, 'store']);
    Route::put('titles/update', [TitleController::class, 'update']);
    Route::delete('titles/delete', [TitleController::class, 'destroy']);

    /**
     @ Genders Routes
     */
    Route::apiResource('genders', GenderController::class);

    /**
     @ Nationalities Routes
     */
    Route::apiResource('nationalities', NationalityController::class);

    /**
     @ Age Units Routes
     */
    Route::apiResource('age-units', AgeUnitController::class);

    /**
     @ Age answers types Routes
     */
    Route::get('answer-types', [AnswerTypeController::class, 'index']);

    /**
     @ Age duration units Routes
     */
    Route::get('duration-units', [DurationUnitController::class, 'index']);

    /**
     @ Age results types Routes
     */
    Route::get('result-types', [ResultTypeController::class, 'index']);

    /**
     @ Referrals Routes
     */
    Route::get('referrals/show', [ReferalController::class, 'show']);
    Route::get('referrals/search', [ReferalController::class, 'searchReferals']);
    Route::get('referrals/{id?}', [ReferalController::class, 'index']);
    Route::post('referrals/create', [ReferalController::class, 'store']);
    Route::put('referrals/update', [ReferalController::class, 'update']);
    Route::delete('referrals/delete', [ReferalController::class, 'destroy']);

    /**
     @ Labs Routes
     */
    Route::get('labs', [LabController::class, 'index']);
    Route::get('collectors', [LabController::class, 'collector']);
    Route::get('labs/show', [LabController::class, 'show']);
    Route::post('labs/create', [LabController::class, 'store']);
    Route::put('labs/update', [LabController::class, 'update']);
    Route::delete('labs/delete', [LabController::class, 'destroy']);

    /**
     @ Price List Routes
     */
    Route::get('price_list/{id?}', [PriceListController::class, 'index']);
    Route::get('price_list/show/{id}', [PriceListController::class, 'show']);
    Route::post('price_list/create', [PriceListController::class, 'store']);
    Route::put('price_list/update', [PriceListController::class, 'update']);
    Route::delete('price_list/delete', [PriceListController::class, 'destroy']);

    /**
     @ categories Routes
     */
    Route::get('categories/{id?}', [CategoryController::class, 'index']);
    Route::get('categories/show', [CategoryController::class, 'show']);
    Route::post('categories/create', [CategoryController::class, 'store']);
    Route::post('categories/import', [CategoryController::class, 'import']);
    Route::put('categories/update', [CategoryController::class, 'update']);
    Route::delete('categories/delete', [CategoryController::class, 'destroy']);

    /**
     @ test groups Routes
     */
    Route::get('test_groups/{id?}', [TestGroupController::class, 'index']);
    Route::get('test_groups/show', [TestGroupController::class, 'show']);
    Route::post('test_groups/create', [TestGroupController::class, 'store']);
    Route::post('test_groups/import', [TestGroupController::class, 'import']);
    Route::put('test_groups/update', [TestGroupController::class, 'update']);
    Route::delete('test_groups/delete', [TestGroupController::class, 'destroy']);

    /**
     @ sample Routes
     */
    Route::get('samples/{id?}', [SampleController::class, 'index']);
    Route::get('samples/show', [SampleController::class, 'show']);
    Route::post('samples/create', [SampleController::class, 'store']);
    Route::post('samples/import', [SampleController::class, 'import']);
    Route::put('samples/update', [SampleController::class, 'update']);
    Route::delete('samples/delete', [SampleController::class, 'destroy']);

    /**
     @ tests Routes
     */
    Route::get('tests/{id?}', [TestController::class, 'index']);
    Route::get('tests/show', [TestController::class, 'show']);
    Route::get('tests/search', [TestController::class, 'search']);
    Route::post('tests/create', [TestController::class, 'store']);
    Route::put('tests/update', [TestController::class, 'update']);
    Route::post('tests/questions', [TestController::class, 'getTestQuestions']);
    Route::post('tests/import', [TestController::class, 'import']);
    Route::delete('tests/delete', [TestController::class, 'destroy']);

    /**
     @ test questions Routes
     */
    Route::get('tests-questions/{id?}', [PatientQuestionController::class, 'index']);
    Route::get('tests-questions/show', [PatientQuestionController::class, 'show']);
    Route::post('tests-questions/create', [PatientQuestionController::class, 'store']);
    Route::put('tests-questions/update', [PatientQuestionController::class, 'update']);
    Route::post('tests-questions/import', [PatientQuestionController::class, 'import']);
    Route::delete('tests-questions/delete', [PatientQuestionController::class, 'destroy']);

    /**
     @ test packages Routes
     */
    Route::get('packages', [PackageController::class, 'index']);
    Route::get('packages/show', [PackageController::class, 'show']);
    Route::post('packages/create', [PackageController::class, 'store']);
    Route::put('packages/update', [PackageController::class, 'update']);
    Route::post('packages/import', [PackageController::class, 'import']);
    Route::delete('packages/delete', [PackageController::class, 'destroy']);

    /**
     @ cultures packages Routes
     */
    Route::get('cultures/{id?}', [CultureController::class, 'index']);
    Route::get('cultures/show', [CultureController::class, 'show']);
    Route::post('cultures/create', [CultureController::class, 'store']);
    Route::put('cultures/update', [CultureController::class, 'update']);
    Route::post('cultures/import', [CultureController::class, 'import']);
    Route::delete('cultures/delete', [CultureController::class, 'destroy']);

    /**
     @ Antibiotics Routes
     */
    Route::get('antibiotics/{id?}', [AntibioticsController::class, 'index']);
    Route::get('antibiotics/show', [AntibioticsController::class, 'show']);
    Route::post('antibiotics/create', [AntibioticsController::class, 'store']);
    Route::put('antibiotics/update', [AntibioticsController::class, 'update']);
    Route::post('antibiotics/import', [AntibioticsController::class, 'import']);
    Route::delete('antibiotics/delete', [AntibioticsController::class, 'destroy']);

    /**
     @ Invoices Routes
     */
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/get-samples/{id}', [InvoiceController::class, 'getTestsSamples']);
    Route::get('invoices/show-pdf', [InvoiceController::class, 'downloadInvoice']);
    Route::post('invoices/create', [InvoiceController::class, 'store']);
    Route::post('invoices/pdf', [InvoiceController::class, 'savePdf']);
    Route::post('invoices/sign', [InvoiceController::class, 'signInvoice']);
    Route::post('invoices/search-name', [InvoiceController::class, 'searchByName']);
    Route::post('invoices/search-phone', [InvoiceController::class, 'searchByPhone']);
    Route::post('invoices/search-code', [InvoiceController::class, 'searchByCode']);
    Route::post('invoices/send', [InvoiceController::class, 'sendInvoice']);
    Route::put('invoices/update', [InvoiceController::class, 'update']);
    Route::post('invoices/update-result', [InvoiceController::class, 'updateResult']);
    Route::post('invoices/add-payment', [InvoiceController::class, 'addPayment']);
    Route::delete('invoices/delete', [InvoiceController::class, 'destroy']);

    /**
     @ Authenticated Invoice View Routes (moved from public)
     */
    Route::get('invoices/patient-medical-records/{id}', [InvoiceController::class, 'patientMedicalRecords'])->middleware('throttle:30,1');
    Route::get('invoices/patient-history/{id}', [InvoiceController::class, 'patientInvoicesHistory'])->middleware('throttle:30,1');
    Route::get('invoices/{id}', [InvoiceController::class, 'show']);

    /**
     @ Payment Methods Routes
     */
    Route::get('payment-methods/{id?}', [PaymentMethodController::class, 'index']);
    Route::get('payment-methods/show', [PaymentMethodController::class, 'show']);
    Route::post('payment-methods/create', [PaymentMethodController::class, 'store']);
    Route::put('payment-methods/update', [PaymentMethodController::class, 'update']);
    Route::delete('payment-methods/delete', [PaymentMethodController::class, 'destroy']);

    /**
     @ Discount Type Routes
     */
    Route::get('discount-types', [DiscountTypeController::class, 'index']);
    Route::get('discount-types/show', [DiscountTypeController::class, 'show']);
    Route::post('discount-types/create', [DiscountTypeController::class, 'store']);
    Route::put('discount-types/update', [DiscountTypeController::class, 'update']);
    Route::delete('discount-types/delete', [DiscountTypeController::class, 'destroy']);

    /**
     @ Bookings Routes
     */
    Route::get('bookings/{id?}', [BookingController::class, 'index']);
    Route::get('bookings/show', [BookingController::class, 'show']);
    Route::get('bookings/patient-bookings/{id}', [BookingController::class, 'getPatientBookings']);
    Route::post('bookings/create', [BookingController::class, 'store']);
    Route::put('bookings/update', [BookingController::class, 'update']);
    Route::delete('bookings/delete', [BookingController::class, 'destroy']);
    Route::get('/reports', [ReportsController::class, 'getReports']);
    Route::get('/reports/dashboard-stats', [ReportsController::class, 'getDashboardStats']);
    Route::get('/accounting-reports', [AccountingReportController::class, 'report']);

    /**
     @ Templates Routes
     */
    Route::get('templates', [TemplateController::class, 'index']);
    Route::get('templates/show', [TemplateController::class, 'show']);
    Route::post('templates/create', [TemplateController::class, 'store']);
    Route::put('templates/update', [TemplateController::class, 'update']);
    Route::delete('templates/delete', [TemplateController::class, 'destroy']);

    /**
     @ Permissions Routes
     */
    Route::get('permissions', [PermissionsController::class, 'index']);
    Route::get('permissions/get-permissions', [PermissionsController::class, 'getPermissions']);
    Route::post('permissions/create', [PermissionsController::class, 'store']);
    Route::put('permissions/update', [PermissionsController::class, 'update']);
    Route::delete('permissions/delete', [PermissionsController::class, 'destroy']);

    /**
     @ Roles Routes
     */
    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles/create', [RoleController::class, 'store']);
    Route::put('roles/update', [RoleController::class, 'update']);
    Route::delete('roles/delete', [RoleController::class, 'destroy']);
    Route::post('roles/assign-role', [RoleController::class, 'assignRole']);
    Route::post('roles/remove-role', [RoleController::class, 'removeRole']);

    /**
     @ Super Admin Routes
     */
    Route::get('super-admin/kpis', [SuperAdminController::class, 'systemKpis']);
    Route::get('super-admin/lab-comparison', [SuperAdminController::class, 'labComparison']);
    Route::get('super-admin/revenue-trend', [SuperAdminController::class, 'revenueTrend']);
    Route::get('super-admin/lab-detail/{labId}', [SuperAdminController::class, 'labDetail']);
    Route::get('super-admin/labs', [SuperAdminController::class, 'labsList']);
    Route::get('super-admin/activity-log', [SuperAdminController::class, 'activityLog']);
    Route::get('super-admin/patients', [SuperAdminController::class, 'patientsList']);
    Route::get('super-admin/patient-detail/{patientId}', [SuperAdminController::class, 'patientDetail']);

    /**
     @ Copy lab data between labs (admin only)
     */
    Route::get('super-admin/lab-data/{labId}/{type}', [\App\Http\Controllers\LabDataCopyController::class, 'list']);
    Route::post('super-admin/lab-data/preview', [\App\Http\Controllers\LabDataCopyController::class, 'preview']);
    Route::post('super-admin/lab-data/copy', [\App\Http\Controllers\LabDataCopyController::class, 'copy']);

    /**
     @ Subscriptions Routes
     */
    Route::get('subscription/my', [SubscriptionController::class, 'mySubscription']);
    Route::get('subscriptions', [SubscriptionController::class, 'index']);
    Route::get('subscriptions/{id}', [SubscriptionController::class, 'show']);
    Route::post('subscriptions/create', [SubscriptionController::class, 'store']);
    Route::put('subscriptions/update', [SubscriptionController::class, 'update']);
    Route::delete('subscriptions/delete', [SubscriptionController::class, 'destroy']);

    /**
     @ Plans Routes
     */
    Route::get('plans', [SubscriptionController::class, 'plans']);

    /**
     * Lab Devices Routes
     */
    Route::get('devices', [LabDeviceController::class, 'index']);
    Route::post('devices/create', [LabDeviceController::class, 'store']);
    Route::put('devices/update', [LabDeviceController::class, 'update']);
    Route::delete('devices/delete', [LabDeviceController::class, 'destroy']);
    Route::post('devices/regenerate-token', [LabDeviceController::class, 'regenerateToken']);

    /**
     * Device Results Routes (user-facing)
     */
    Route::get('device-results', [DeviceResultController::class, 'index']);
    Route::post('device-results/{id}/apply', [DeviceResultController::class, 'applyResult']);
    Route::post('device-results/{id}/match', [DeviceResultController::class, 'manualMatch']);

    /**
     * Lab Settings Routes
     */
    Route::get('lab-settings', [LabSettingController::class, 'show']);
    Route::post('lab-settings', [LabSettingController::class, 'update']);
    Route::delete('lab-settings/logo', [LabSettingController::class, 'removeLogo']);
    Route::delete('lab-settings/background', [LabSettingController::class, 'removeBackground']);
    Route::post('lab-settings/reset', [LabSettingController::class, 'reset']);

    Route::prefix('inventory')->group(function (): void {
        Route::get('options', [InventoryController::class, 'options']);
        Route::get('/', [InventoryController::class, 'index']);
        Route::post('items', [InventoryController::class, 'storeItem']);
        Route::post('kits', [InventoryController::class, 'receiveKit']);
        Route::post('bindings', [InventoryController::class, 'storeBinding']);
        Route::post('kits/{kit}/adjust', [InventoryController::class, 'adjustKit']);
        Route::get('report', [InventoryController::class, 'report']);
        Route::get('report/export', [InventoryController::class, 'export']);
        Route::get('invoices/{invoice}', [InventoryController::class, 'invoice']);
        Route::post('invoices/{invoice}/repeat', [InventoryController::class, 'repeat']);
    });

});
