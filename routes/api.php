<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogueController;
use App\Http\Controllers\Api\Customer;
use App\Http\Controllers\Api\Merchant;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PrivateFileController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\Technician;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /* ---------------- Public catalogue & discovery ---------------- */
    Route::get('meta', [CatalogueController::class, 'meta']);
    Route::get('phone-models', [CatalogueController::class, 'phoneModels']);
    Route::get('home', [CatalogueController::class, 'home']);
    Route::get('search', [CatalogueController::class, 'search']);
    Route::get('products', [CatalogueController::class, 'products']);
    Route::get('products/{slug}', [CatalogueController::class, 'product']);
    Route::get('shops', [CatalogueController::class, 'shops']);
    Route::get('shops/{slug}', [CatalogueController::class, 'shop']);
    Route::get('technicians', [CatalogueController::class, 'technicians']);
    Route::get('technicians/{slug}', [CatalogueController::class, 'technician']);

    /* ---------------- Authentication ---------------- */
    Route::prefix('auth')->group(function () {
        Route::post('otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:otp');
        Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify');
        Route::post('staff/login', [AuthController::class, 'staffLogin'])->middleware('throttle:login');
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'read']);

        Route::get('repair-images/{image}', [PrivateFileController::class, 'repairImage']);
        Route::get('ticket-attachments/{attachment}', [PrivateFileController::class, 'ticketAttachment']);
        Route::get('shop-documents/{document}', [PrivateFileController::class, 'shopDocument']);
        Route::get('technician-documents/{document}', [PrivateFileController::class, 'technicianDocument']);

        /* Support tickets — any signed-in user can raise and follow a complaint. */
        Route::get('tickets', [SupportTicketController::class, 'index']);
        Route::post('tickets', [SupportTicketController::class, 'store'])->middleware('throttle:writes');
        Route::get('tickets/{ticket}', [SupportTicketController::class, 'show']);
        Route::post('tickets/{ticket}/messages', [SupportTicketController::class, 'reply'])->middleware('throttle:writes');

        /* ---------------- Customer portal ---------------- */
        Route::middleware('role:customer')->group(function () {
            Route::get('enquiries', [Customer\EnquiryController::class, 'index']);
            Route::post('enquiries', [Customer\EnquiryController::class, 'store'])->middleware('throttle:writes');

            Route::get('orders', [Customer\OrderController::class, 'index']);
            Route::post('orders', [Customer\OrderController::class, 'store'])->middleware('throttle:writes');
            Route::get('orders/{order}', [Customer\OrderController::class, 'show']);
            Route::post('orders/{order}/accept', [Customer\OrderController::class, 'accept']);
            Route::post('orders/{order}/cancel', [Customer\OrderController::class, 'cancel']);
            Route::post('orders/{order}/confirm-receipt', [Customer\OrderController::class, 'confirmReceipt']);
            Route::post('orders/{order}/reviews', [Customer\OrderController::class, 'review']);

            Route::get('repairs', [Customer\RepairController::class, 'index']);
            Route::post('repairs', [Customer\RepairController::class, 'store'])->middleware('throttle:writes');
            Route::get('repairs/{repair}', [Customer\RepairController::class, 'show']);
            Route::post('repairs/{repair}/quotation', [Customer\RepairController::class, 'respondQuotation']);
            Route::post('repairs/{repair}/handover', [Customer\RepairController::class, 'confirmHandover']);
            Route::post('repairs/{repair}/cancel', [Customer\RepairController::class, 'cancel']);
            Route::post('repairs/{repair}/review', [Customer\RepairController::class, 'review']);
        });

        /* ---------------- Merchant portal ---------------- */
        Route::middleware('role:merchant')->prefix('merchant')->group(function () {
            Route::get('shop', [Merchant\ShopController::class, 'show']);
            Route::post('shop', [Merchant\ShopController::class, 'store']);
            Route::post('shop/update', [Merchant\ShopController::class, 'update']); // multipart-friendly update
            Route::delete('shop/photos/{photo}', [Merchant\ShopController::class, 'deletePhoto']);

            Route::get('dashboard', Merchant\DashboardController::class);

            Route::get('products', [Merchant\ProductController::class, 'index']);
            Route::post('products', [Merchant\ProductController::class, 'store']);
            Route::post('products/confirm-stock', [Merchant\ProductController::class, 'confirmAllStock']);
            Route::get('products/{product}', [Merchant\ProductController::class, 'show']);
            Route::post('products/{product}', [Merchant\ProductController::class, 'update']); // multipart-friendly update
            Route::delete('products/{product}', [Merchant\ProductController::class, 'destroy']);
            Route::post('products/{product}/toggle', [Merchant\ProductController::class, 'toggle']);
            Route::post('products/{product}/stock', [Merchant\ProductController::class, 'updateStock']);
            Route::post('products/{product}/confirm-stock', [Merchant\ProductController::class, 'confirmStock']);
            Route::get('products/{product}/stock-logs', [Merchant\ProductController::class, 'stockLogs']);
            Route::delete('products/{product}/images/{image}', [Merchant\ProductController::class, 'deleteImage']);

            Route::get('enquiries', [Merchant\EnquiryController::class, 'index']);
            Route::post('enquiries/{enquiry}/reply', [Merchant\EnquiryController::class, 'reply']);

            Route::get('orders', [Merchant\OrderController::class, 'index']);
            Route::get('orders/{order}', [Merchant\OrderController::class, 'show']);
            Route::post('orders/{order}/quote', [Merchant\OrderController::class, 'quote']);
            Route::post('orders/{order}/reject', [Merchant\OrderController::class, 'reject']);
            Route::post('orders/{order}/confirm', [Merchant\OrderController::class, 'confirm']);
            Route::post('orders/{order}/ready', [Merchant\OrderController::class, 'ready']);
            Route::post('orders/{order}/handover', [Merchant\OrderController::class, 'handover']);
            Route::post('orders/{order}/cancel', [Merchant\OrderController::class, 'cancel']);
            Route::post('orders/{order}/payments', [Merchant\OrderController::class, 'payment']);

            Route::get('reports', [Merchant\ReportController::class, 'index']);
            Route::get('settlements', [Merchant\ReportController::class, 'settlements']);
        });

        /* ---------------- Technician portal ---------------- */
        Route::middleware('role:technician')->prefix('technician')->group(function () {
            Route::get('profile', [Technician\ProfileController::class, 'show']);
            Route::post('profile', [Technician\ProfileController::class, 'store']);
            Route::post('profile/update', [Technician\ProfileController::class, 'update']);

            Route::get('dashboard', [Technician\RepairController::class, 'dashboard']);
            Route::get('reports', [Technician\RepairController::class, 'reports']);
            Route::get('repairs', [Technician\RepairController::class, 'index']);
            Route::get('repairs/{repair}', [Technician\RepairController::class, 'show']);
            foreach (['accept', 'reject', 'diagnosis', 'quotation', 'start', 'progress', 'part', 'cancel', 'complete'] as $action) {
                Route::post("repairs/{repair}/{$action}", [Technician\RepairController::class, $action]);
            }
            Route::post('repairs/{repair}/testing', [Technician\RepairController::class, 'startTesting']);
            Route::post('repairs/{repair}/test-result', [Technician\RepairController::class, 'testResult']);
        });

        /* ---------------- Staff: admin + support operators ---------------- */
        Route::middleware('role:admin,support')->prefix('admin')->group(function () {
            Route::get('dashboard', Admin\DashboardController::class);
            Route::get('tickets', [Admin\TicketController::class, 'index']);
            Route::get('tickets/staff', [Admin\TicketController::class, 'staff']);
            Route::get('tickets/{ticket}', [SupportTicketController::class, 'show']);
            Route::put('tickets/{ticket}', [Admin\TicketController::class, 'update']);
            Route::post('tickets/{ticket}/messages', [SupportTicketController::class, 'reply']);
            Route::get('orders', [Admin\OrderController::class, 'index']);
            Route::get('orders/{order}', [Admin\OrderController::class, 'show']);
            Route::get('repairs', [Admin\RepairController::class, 'index']);
            Route::get('repairs/{repair}', [Admin\RepairController::class, 'show']);
            Route::get('users', [Admin\UserController::class, 'index']);
            Route::get('users/{user}', [Admin\UserController::class, 'show']);
        });

        /* ---------------- Admin only ---------------- */
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::post('users/staff', [Admin\UserController::class, 'storeStaff']);
            Route::post('users/{user}/suspend', [Admin\UserController::class, 'suspend']);
            Route::post('users/{user}/activate', [Admin\UserController::class, 'activate']);

            Route::get('shops', [Admin\VerificationController::class, 'shops']);
            Route::get('shops/{shop}', [Admin\VerificationController::class, 'shop'])->withTrashed();
            Route::post('shops/{shop}/status', [Admin\VerificationController::class, 'updateShop']);
            Route::get('technicians', [Admin\VerificationController::class, 'technicians']);
            Route::get('technicians/{technician}', [Admin\VerificationController::class, 'technician'])->withTrashed();
            Route::post('technicians/{technician}/status', [Admin\VerificationController::class, 'updateTechnician']);

            Route::get('taxonomy/{type}', [Admin\TaxonomyController::class, 'index']);
            Route::post('taxonomy/{type}', [Admin\TaxonomyController::class, 'store']);
            Route::put('taxonomy/{type}/{id}', [Admin\TaxonomyController::class, 'update']);
            Route::delete('taxonomy/{type}/{id}', [Admin\TaxonomyController::class, 'destroy']);

            Route::get('products', [Admin\ProductModerationController::class, 'index']);
            Route::post('products/{product}/moderate', [Admin\ProductModerationController::class, 'update']);

            Route::post('orders/{order}/cancel', [Admin\OrderController::class, 'cancel']);
            Route::post('orders/{order}/complete', [Admin\OrderController::class, 'complete']);
            Route::post('orders/{order}/adjust', [Admin\OrderController::class, 'adjust']);
            Route::post('repairs/{repair}/cancel', [Admin\RepairController::class, 'cancel']);

            Route::get('reviews', [Admin\ReviewController::class, 'index']);
            Route::post('reviews/{review}/moderate', [Admin\ReviewController::class, 'moderate']);

            Route::get('refunds', [Admin\RefundController::class, 'index']);
            Route::post('refunds', [Admin\RefundController::class, 'store']);
            Route::post('refunds/{refund}/process', [Admin\RefundController::class, 'process']);

            Route::get('settlements', [Admin\SettlementController::class, 'index']);
            Route::get('settlements/outstanding', [Admin\SettlementController::class, 'outstanding']);
            Route::post('settlements', [Admin\SettlementController::class, 'store']);
            Route::get('settlements/{settlement}', [Admin\SettlementController::class, 'show']);
            Route::put('settlements/{settlement}', [Admin\SettlementController::class, 'update']);

            Route::apiResource('commissions', Admin\CommissionController::class)->except('show');
            Route::post('notification-templates/preview', [Admin\NotificationTemplateController::class, 'preview']);
            Route::apiResource('notification-templates', Admin\NotificationTemplateController::class)->except('show')->parameters(['notification-templates' => 'template']);

            Route::get('reports', [Admin\SystemController::class, 'reports']);
            Route::get('settings', [Admin\SystemController::class, 'settings']);
            Route::put('settings', [Admin\SystemController::class, 'updateSettings']);
            Route::get('activity-logs', [Admin\SystemController::class, 'activityLogs']);
            Route::get('search-logs', [Admin\SystemController::class, 'searchLogs']);

            Route::get('system-logs', [Admin\LogController::class, 'files']);
            Route::get('system-logs/{file}', [Admin\LogController::class, 'show'])->where('file', '[A-Za-z0-9._-]+');
            Route::get('system-logs/{file}/download', [Admin\LogController::class, 'download'])->where('file', '[A-Za-z0-9._-]+');
            Route::delete('system-logs/{file}', [Admin\LogController::class, 'clear'])->where('file', '[A-Za-z0-9._-]+');
        });
    });
});
