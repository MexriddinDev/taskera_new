<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdAccountController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\DepartmentController;
use App\Modules\Ticketing\Presentation\Http\Controllers\TicketController;
use App\Modules\Telegram\Presentation\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\Api\KnowledgeArticleController;
use App\Modules\Knowledge\Presentation\Http\Controllers\KnowledgeController;
use App\Modules\Asset\Presentation\Http\Controllers\AssetController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\ManufacturerController;
use App\Http\Controllers\Api\AssetModelController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\SoftwareProductController;
use App\Http\Controllers\Api\SoftwareLicenseController;
use App\Http\Controllers\Api\AssetController as AssetApiController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\TaskController;
use App\Modules\Notification\Presentation\Http\Controllers\NotificationController;
use App\Modules\Notification\Presentation\Http\Controllers\NotificationTemplateController;
use App\Modules\Notification\Presentation\Http\Controllers\UserNotificationPreferenceController;
use App\Http\Controllers\Api\UserDepartmentStatsController;

Route::prefix('v1')->group(function () {
    // Reference / Lookup tables
    Route::get('/references/locales', [ReferenceController::class, 'locales']);
    Route::get('/references/timezones', [ReferenceController::class, 'timezones']);
    Route::get('/references/employment-statuses', [ReferenceController::class, 'employmentStatuses']);
    Route::get('/references/ticket-statuses', [ReferenceController::class, 'ticketStatuses']);
    Route::get('/references/ticket-status-transitions', [ReferenceController::class, 'ticketStatusTransitions']);
    Route::get('/references/ticket-priorities', [ReferenceController::class, 'ticketPriorities']);
    Route::get('/references/ticket-sources', [ReferenceController::class, 'ticketSources']);
    Route::get('/references/comment-types', [ReferenceController::class, 'commentTypes']);
    Route::get('/references/comment-sources', [ReferenceController::class, 'commentSources']);
    Route::get('/references/attachment-types', [ReferenceController::class, 'attachmentTypes']);
    Route::get('/references/notification-channels', [ReferenceController::class, 'notificationChannels']);
    Route::get('/references/asset-types', [ReferenceController::class, 'assetTypes']);
    Route::get('/references/asset-statuses', [ReferenceController::class, 'assetStatuses']);
    Route::get('/references/relationship-types', [ReferenceController::class, 'relationshipTypes']);
    Route::get('/references/article-types', [ReferenceController::class, 'articleTypes']);
    Route::get('/references/workflow-entity-types', [ReferenceController::class, 'workflowEntityTypes']);
    Route::get('/references/integration-types', [ReferenceController::class, 'integrationTypes']);

    // Fayl yuklab olish — faqat IMZOLANGAN vaqtinchalik havola orqali (IDOR himoyasi).
    // Havolani AttachmentResource 30 daqiqalik imzo bilan generatsiya qiladi.
    Route::get('/attachments/{id}/download', [AttachmentController::class, 'download'])
        ->name('attachments.download')
        ->middleware('signed:relative');

    // Auth APIs (brute-force himoyasi: 5 urinish/daqiqa)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // Yangi xodim uchun pochta (AD) ochish — SMS orqali telefon tasdiqlash
    Route::get('/ad-account/prepare', [AdAccountController::class, 'prepare']);
    Route::post('/ad-account/check-employee', [AdAccountController::class, 'checkEmployee']);
    Route::post('/ad-account/check-bxm', [AdAccountController::class, 'checkBxm']);
    // SMS bombing/xarajat hujumi himoyasi: phone + IP bo'yicha maxsus rate limiter
    Route::post('/ad-account/send-code', [AdAccountController::class, 'sendCode'])->middleware('throttle:sms');
    Route::post('/ad-account/verify-code', [AdAccountController::class, 'verifyCode'])->middleware('throttle:10,1');
    Route::post('/ad-account/exchange', [AdAccountController::class, 'createExchange']);
    Route::get('/ad-account/exchange', function (\Illuminate\Http\Request $request) {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Pochta (AD) hisobi yaratish faqat POST so\'rovi orqali amalga oshiriladi.',
                'method_required' => 'POST',
                'endpoint' => '/api/v1/ad-account/exchange',
                'params' => ['pinfl', 'phone', 'bxm_code'],
            ], 405);
        }

        return redirect('/ad-account');
    });
    Route::post('/ad-account/reset-password', [AdAccountController::class, 'resetPassword']);
    Route::post('/ad-account/link-bxm', [AdAccountController::class, 'linkBxm']);

    Route::middleware('auth:sanctum')->group(function () {
        // Executive Dashboard Dynamic APIs.
        // Boshqaruv paneli — barcha zayavkalarning umumiy ko'rinishi, shuning
        // uchun `dashboard.view` talab qilinadi (support xodimda bu huquq yo'q).
        Route::middleware('permission:dashboard.view')->group(function () {
            Route::get('/dashboard/stats', [DashboardApiController::class, 'stats']);
            Route::get('/dashboard/tickets', [DashboardApiController::class, 'tickets']);
            Route::post('/dashboard/quick-ticket', [DashboardApiController::class, 'quickTicket']);
            Route::get('/dashboard/search', [DashboardApiController::class, 'search']);
        });
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/avatar', [AuthController::class, 'updateAvatar']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Profile
        Route::put('/profile', [ProfileController::class, 'update']);
        // Support paneli — xodimlar kesimida zayavkalar va SLA ko'rsatkichlari.
        // Alohida huquq: navbatni ko'rish (`tickets.view`) panelni ochmaydi,
        // panel RBAC dan har bir rolga alohida biriktiriladi.
        Route::middleware('permission:support_panel.view')->group(function () {
            Route::get('/support-panel/staff', [\App\Http\Controllers\Api\SupportPanelController::class, 'staff']);
            Route::get('/support-panel/staff/{userId}/tickets', [\App\Http\Controllers\Api\SupportPanelController::class, 'tickets']);
        });

        Route::get('/users/department-stats', [UserDepartmentStatsController::class, 'departmentStats']);
        Route::get('/users/requester-stats', [UserDepartmentStatsController::class, 'requesterStats']);
        Route::get('/users/department/{id}/tickets', [UserDepartmentStatsController::class, 'departmentTickets']);
        Route::get('/users/{id}', [ProfileController::class, 'show'])->where('id', '[0-9]+');
        Route::get('/users/{id}/summary', [ProfileController::class, 'summary'])->where('id', '[0-9]+');

        // Tickets (Web Sites Frontend)
        Route::get('/tickets/stats', [TicketController::class, 'stats']);
        Route::get('/tickets/monitoring', [TicketController::class, 'monitoring']);
        // Zayavkani biriktirish oynasi uchun xodimlar ro'yxati.
        // `/tickets/{id}` dan OLDIN turishi shart — aks holda "assignable-staff"
        // id sifatida talqin qilinadi.
        Route::get('/tickets/assignable-staff', [TicketController::class, 'assignableStaff']);
        Route::get('/tickets/executive-monitoring', [TicketController::class, 'executiveMonitoring']);
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::post('/tickets', [TicketController::class, 'store']);
        Route::get('/tickets/{id}', [TicketController::class, 'show']);
        Route::put('/tickets/{id}', [TicketController::class, 'update']);
        Route::delete('/tickets/{id}', [TicketController::class, 'destroy']);
        Route::post('/tickets/{id}/transition', [TicketController::class, 'transition']);
        Route::post('/tickets/{id}/assign', [TicketController::class, 'assign']);

        // Comments
        Route::get('/tickets/{ticketId}/comments', [CommentController::class, 'index']);
        Route::post('/tickets/{ticketId}/comments', [CommentController::class, 'store']);
        Route::put('/comments/{id}', [CommentController::class, 'update']);
        Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

        // Attachments
        Route::post('/attachments/upload', [AttachmentController::class, 'upload']);
        Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy']);

        // Notifications
        Route::get('/notification-templates', [NotificationTemplateController::class, 'index']);
        Route::get('/notification-templates/{id}', [NotificationTemplateController::class, 'show']);
        Route::middleware('permission:roles.manage,users.manage')->group(function () {
            Route::post('/notification-templates', [NotificationTemplateController::class, 'store']);
            Route::put('/notification-templates/{id}', [NotificationTemplateController::class, 'update']);
            Route::delete('/notification-templates/{id}', [NotificationTemplateController::class, 'destroy']);
        });

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/{id}', [NotificationController::class, 'show']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

        Route::get('/notification-preferences', [UserNotificationPreferenceController::class, 'index']);
        Route::put('/notification-preferences', [UserNotificationPreferenceController::class, 'update']);

        // Collaboration - Chat
        Route::get('/chat/conversations', [ChatController::class, 'index']);
        Route::post('/chat/conversations', [ChatController::class, 'store']);
        Route::get('/chat/conversations/{id}', [ChatController::class, 'show']);
        Route::post('/chat/conversations/{id}/messages', [ChatController::class, 'sendMessage']);
        Route::get('/chat/conversations/{id}/messages', [ChatController::class, 'messages']);

        // Collaboration - Calendar
        Route::get('/calendar-events', [CalendarEventController::class, 'index']);
        Route::post('/calendar-events', [CalendarEventController::class, 'store']);
        Route::get('/calendar-events/{id}', [CalendarEventController::class, 'show']);
        Route::put('/calendar-events/{id}', [CalendarEventController::class, 'update']);
        Route::delete('/calendar-events/{id}', [CalendarEventController::class, 'destroy']);

        // Collaboration - Tasks
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::get('/tasks/{id}', [TaskController::class, 'show']);
        Route::put('/tasks/{id}', [TaskController::class, 'update']);
        Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);

        // Audit Logs (Protected by audit.view)
        Route::middleware('permission:audit.view')->group(function () {
            Route::get('/audit-logs', [AuditLogController::class, 'index']);
            Route::get('/audit-logs/{id}', [AuditLogController::class, 'show']);
        });

        // Dynamic Roles & Permissions APIs (Protected by roles.manage)
        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('/roles', [RoleController::class, 'index']);
            Route::post('/roles', [RoleController::class, 'store']);
            Route::put('/roles/{id}', [RoleController::class, 'update']);
            Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
            Route::get('/permissions', [RoleController::class, 'permissions']);
            Route::post('/permissions', [RoleController::class, 'storePermission']);
            Route::put('/permissions/{id}', [RoleController::class, 'updatePermission']);
            Route::delete('/permissions/{id}', [RoleController::class, 'destroyPermission']);
            Route::get('/users/roles', [RoleController::class, 'usersWithRoles']);
            Route::post('/users/{id}/assign-role', [RoleController::class, 'assignUserRole'])->where('id', '[0-9]+');
        });

        // Dynamic Departments & Directory APIs
        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::middleware('permission:departments.manage')->group(function () {
            Route::post('/departments', [DepartmentController::class, 'store']);
            Route::put('/departments/{id}', [DepartmentController::class, 'update']);
            Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);
        });

        // Organization / HR APIs
        Route::get('/regions', [\App\Http\Controllers\Api\RegionController::class, 'index']);
        Route::get('/regions/{id}', [\App\Http\Controllers\Api\RegionController::class, 'show']);
        Route::get('/branches', [\App\Http\Controllers\Api\BranchController::class, 'index']);
        Route::get('/branches/{id}', [\App\Http\Controllers\Api\BranchController::class, 'show']);
        Route::get('/positions', [\App\Http\Controllers\Api\PositionController::class, 'index']);
        Route::get('/positions/{id}', [\App\Http\Controllers\Api\PositionController::class, 'show']);
        Route::get('/employees', [\App\Http\Controllers\Api\EmployeeController::class, 'index']);
        Route::get('/employees/{id}', [\App\Http\Controllers\Api\EmployeeController::class, 'show']);

        Route::middleware('permission:departments.manage')->group(function () {
            Route::post('/regions', [\App\Http\Controllers\Api\RegionController::class, 'store']);
            Route::put('/regions/{id}', [\App\Http\Controllers\Api\RegionController::class, 'update']);
            Route::delete('/regions/{id}', [\App\Http\Controllers\Api\RegionController::class, 'destroy']);

            Route::post('/branches', [\App\Http\Controllers\Api\BranchController::class, 'store']);
            Route::put('/branches/{id}', [\App\Http\Controllers\Api\BranchController::class, 'update']);
            Route::delete('/branches/{id}', [\App\Http\Controllers\Api\BranchController::class, 'destroy']);

            Route::post('/positions', [\App\Http\Controllers\Api\PositionController::class, 'store']);
            Route::put('/positions/{id}', [\App\Http\Controllers\Api\PositionController::class, 'update']);
            Route::delete('/positions/{id}', [\App\Http\Controllers\Api\PositionController::class, 'destroy']);

            Route::post('/employees', [\App\Http\Controllers\Api\EmployeeController::class, 'store']);
            Route::put('/employees/{id}', [\App\Http\Controllers\Api\EmployeeController::class, 'update']);
            Route::delete('/employees/{id}', [\App\Http\Controllers\Api\EmployeeController::class, 'destroy']);
        });

        // ITSM Master Data APIs
        Route::get('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
        Route::get('/categories/{id}', [\App\Http\Controllers\Api\CategoryController::class, 'show']);
        Route::get('/sla-rules', [\App\Http\Controllers\Api\SlaRuleController::class, 'index']);
        Route::get('/sla-rules/teams', [\App\Http\Controllers\Api\SlaRuleController::class, 'teams']);
        Route::get('/sla-rules/priorities', [\App\Http\Controllers\Api\SlaRuleController::class, 'priorities']);
        Route::get('/services', [\App\Http\Controllers\Api\ServiceController::class, 'index']);
        Route::get('/services/{id}', [\App\Http\Controllers\Api\ServiceController::class, 'show']);
        Route::get('/service-offerings', [\App\Http\Controllers\Api\ServiceOfferingController::class, 'index']);
        Route::get('/service-offerings/{id}', [\App\Http\Controllers\Api\ServiceOfferingController::class, 'show']);
        Route::get('/locations', [\App\Http\Controllers\Api\LocationController::class, 'index']);
        Route::get('/locations/{id}', [\App\Http\Controllers\Api\LocationController::class, 'show']);
        Route::get('/resolution-codes', [\App\Http\Controllers\Api\ResolutionCodeController::class, 'index']);
        Route::get('/resolution-codes/{id}', [\App\Http\Controllers\Api\ResolutionCodeController::class, 'show']);

        // Elektron ruxsatnoma — tashrifchilar qaydi (admin/superadmin).
        Route::middleware('permission:permits.manage')->group(function () {
            Route::get('/permits', [\App\Http\Controllers\Api\PermitController::class, 'index']);
            Route::post('/permits', [\App\Http\Controllers\Api\PermitController::class, 'store']);
            Route::put('/permits/{id}', [\App\Http\Controllers\Api\PermitController::class, 'update']);
            Route::delete('/permits/{id}', [\App\Http\Controllers\Api\PermitController::class, 'destroy']);
        });

        Route::middleware('permission:services.manage,sla.manage')->group(function () {
            Route::post('/sla-rules', [\App\Http\Controllers\Api\SlaRuleController::class, 'store']);
            Route::put('/sla-rules/{id}', [\App\Http\Controllers\Api\SlaRuleController::class, 'update']);
            Route::delete('/sla-rules/{id}', [\App\Http\Controllers\Api\SlaRuleController::class, 'destroy']);
            Route::post('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'store']);
            Route::put('/categories/{id}', [\App\Http\Controllers\Api\CategoryController::class, 'update']);
            Route::delete('/categories/{id}', [\App\Http\Controllers\Api\CategoryController::class, 'destroy']);

            Route::post('/services', [\App\Http\Controllers\Api\ServiceController::class, 'store']);
            Route::put('/services/{id}', [\App\Http\Controllers\Api\ServiceController::class, 'update']);
            Route::delete('/services/{id}', [\App\Http\Controllers\Api\ServiceController::class, 'destroy']);

            Route::post('/service-offerings', [\App\Http\Controllers\Api\ServiceOfferingController::class, 'store']);
            Route::put('/service-offerings/{id}', [\App\Http\Controllers\Api\ServiceOfferingController::class, 'update']);
            Route::delete('/service-offerings/{id}', [\App\Http\Controllers\Api\ServiceOfferingController::class, 'destroy']);

            Route::post('/locations', [\App\Http\Controllers\Api\LocationController::class, 'store']);
            Route::put('/locations/{id}', [\App\Http\Controllers\Api\LocationController::class, 'update']);
            Route::delete('/locations/{id}', [\App\Http\Controllers\Api\LocationController::class, 'destroy']);

            Route::post('/resolution-codes', [\App\Http\Controllers\Api\ResolutionCodeController::class, 'store']);
            Route::put('/resolution-codes/{id}', [\App\Http\Controllers\Api\ResolutionCodeController::class, 'update']);
            Route::delete('/resolution-codes/{id}', [\App\Http\Controllers\Api\ResolutionCodeController::class, 'destroy']);
        });

        // Asset / CMDB APIs
        Route::get('/manufacturers', [ManufacturerController::class, 'index']);
        Route::get('/manufacturers/{id}', [ManufacturerController::class, 'show']);
        Route::get('/asset-models', [AssetModelController::class, 'index']);
        Route::get('/asset-models/{id}', [AssetModelController::class, 'show']);
        Route::get('/vendors', [VendorController::class, 'index']);
        Route::get('/vendors/{id}', [VendorController::class, 'show']);
        Route::get('/software-products', [SoftwareProductController::class, 'index']);
        Route::get('/software-products/{id}', [SoftwareProductController::class, 'show']);
        Route::get('/software-licenses', [SoftwareLicenseController::class, 'index']);
        Route::get('/software-licenses/{id}', [SoftwareLicenseController::class, 'show']);
        Route::get('/assets', [AssetApiController::class, 'index']);
        Route::get('/assets/{id}', [AssetApiController::class, 'show']);

        Route::middleware('permission:assets.manage')->group(function () {
            Route::post('/manufacturers', [ManufacturerController::class, 'store']);
            Route::put('/manufacturers/{id}', [ManufacturerController::class, 'update']);
            Route::delete('/manufacturers/{id}', [ManufacturerController::class, 'destroy']);

            Route::post('/asset-models', [AssetModelController::class, 'store']);
            Route::put('/asset-models/{id}', [AssetModelController::class, 'update']);
            Route::delete('/asset-models/{id}', [AssetModelController::class, 'destroy']);

            Route::post('/vendors', [VendorController::class, 'store']);
            Route::put('/vendors/{id}', [VendorController::class, 'update']);
            Route::delete('/vendors/{id}', [VendorController::class, 'destroy']);

            Route::post('/software-products', [SoftwareProductController::class, 'store']);
            Route::put('/software-products/{id}', [SoftwareProductController::class, 'update']);
            Route::delete('/software-products/{id}', [SoftwareProductController::class, 'destroy']);

            Route::post('/software-licenses', [SoftwareLicenseController::class, 'store']);
            Route::put('/software-licenses/{id}', [SoftwareLicenseController::class, 'update']);
            Route::delete('/software-licenses/{id}', [SoftwareLicenseController::class, 'destroy']);

            Route::post('/assets', [AssetApiController::class, 'store']);
            Route::put('/assets/{id}', [AssetApiController::class, 'update']);
            Route::delete('/assets/{id}', [AssetApiController::class, 'destroy']);
            Route::post('/assets/discover', [AssetController::class, 'discover']);
        });

        // Knowledge & CMDB Asset APIs
        Route::get('/knowledge/articles', [KnowledgeArticleController::class, 'index']);
        Route::get('/knowledge/articles/{id}', [KnowledgeArticleController::class, 'show']);
        Route::post('/knowledge/articles/{id}/feedback', [KnowledgeArticleController::class, 'feedback']);
        Route::get('/knowledge/search', [KnowledgeArticleController::class, 'search']);

        Route::middleware('permission:knowledge.manage')->group(function () {
            Route::post('/knowledge/articles', [KnowledgeArticleController::class, 'store']);
            Route::put('/knowledge/articles/{id}', [KnowledgeArticleController::class, 'update']);
            Route::delete('/knowledge/articles/{id}', [KnowledgeArticleController::class, 'destroy']);
            Route::post('/knowledge/articles/{id}/publish', [KnowledgeArticleController::class, 'publish']);
            Route::post('/knowledge/articles/{id}/archive', [KnowledgeArticleController::class, 'archive']);
        });

        // Problem Management (Protected by problems.manage / problems.view)
        Route::get('/problems', [\App\Http\Controllers\Api\ProblemController::class, 'index']);
        Route::get('/problems/{id}', [\App\Http\Controllers\Api\ProblemController::class, 'show']);
        Route::middleware('permission:problems.manage')->group(function () {
            Route::post('/problems', [\App\Http\Controllers\Api\ProblemController::class, 'store']);
            Route::put('/problems/{id}', [\App\Http\Controllers\Api\ProblemController::class, 'update']);
            Route::delete('/problems/{id}', [\App\Http\Controllers\Api\ProblemController::class, 'destroy']);
            Route::post('/problems/{id}/link-ticket', [\App\Http\Controllers\Api\ProblemController::class, 'linkTicket']);
        });

        // Change Management (Protected by changes.manage / changes.approve)
        Route::get('/changes', [\App\Http\Controllers\Api\ChangeController::class, 'index']);
        Route::get('/changes/{id}', [\App\Http\Controllers\Api\ChangeController::class, 'show']);
        Route::middleware('permission:changes.manage')->group(function () {
            Route::post('/changes', [\App\Http\Controllers\Api\ChangeController::class, 'store']);
            Route::put('/changes/{id}', [\App\Http\Controllers\Api\ChangeController::class, 'update']);
            Route::delete('/changes/{id}', [\App\Http\Controllers\Api\ChangeController::class, 'destroy']);
        });
        Route::middleware('permission:changes.approve,changes.manage')->group(function () {
            Route::post('/changes/{id}/approve', [\App\Http\Controllers\Api\ChangeController::class, 'approve']);
            Route::post('/changes/{id}/reject', [\App\Http\Controllers\Api\ChangeController::class, 'reject']);
        });

        // Maintenance Windows
        Route::get('/maintenance-windows', [\App\Http\Controllers\Api\MaintenanceWindowController::class, 'index']);
        Route::get('/maintenance-windows/{id}', [\App\Http\Controllers\Api\MaintenanceWindowController::class, 'show']);
        Route::middleware('permission:changes.manage')->group(function () {
            Route::post('/maintenance-windows', [\App\Http\Controllers\Api\MaintenanceWindowController::class, 'store']);
            Route::put('/maintenance-windows/{id}', [\App\Http\Controllers\Api\MaintenanceWindowController::class, 'update']);
            Route::delete('/maintenance-windows/{id}', [\App\Http\Controllers\Api\MaintenanceWindowController::class, 'destroy']);
        });

        // Service Catalog
        Route::get('/catalog/items', [\App\Http\Controllers\Api\ServiceCatalogItemController::class, 'index']);
        Route::get('/catalog/items/{id}', [\App\Http\Controllers\Api\ServiceCatalogItemController::class, 'show']);
        Route::middleware('permission:services.manage')->group(function () {
            Route::post('/catalog/items', [\App\Http\Controllers\Api\ServiceCatalogItemController::class, 'store']);
            Route::put('/catalog/items/{id}', [\App\Http\Controllers\Api\ServiceCatalogItemController::class, 'update']);
            Route::delete('/catalog/items/{id}', [\App\Http\Controllers\Api\ServiceCatalogItemController::class, 'destroy']);
        });

        // Service Requests
        Route::get('/service-requests', [\App\Http\Controllers\Api\ServiceRequestController::class, 'index']);
        Route::get('/service-requests/{id}', [\App\Http\Controllers\Api\ServiceRequestController::class, 'show']);

        // Approval Requests
        Route::get('/approval-requests', [\App\Http\Controllers\Api\ApprovalRequestController::class, 'index']);
        Route::get('/approval-requests/{id}', [\App\Http\Controllers\Api\ApprovalRequestController::class, 'show']);
        Route::post('/approval-requests/{id}/approve', [\App\Http\Controllers\Api\ApprovalRequestController::class, 'approve']);
        Route::post('/approval-requests/{id}/reject', [\App\Http\Controllers\Api\ApprovalRequestController::class, 'reject']);

        // Workflows (Protected by workflows.manage)
        Route::middleware('permission:workflows.manage')->group(function () {
            Route::get('/workflows', [\App\Http\Controllers\Api\WorkflowController::class, 'index']);
            Route::post('/workflows', [\App\Http\Controllers\Api\WorkflowController::class, 'store']);
            Route::get('/workflows/{id}', [\App\Http\Controllers\Api\WorkflowController::class, 'show']);
            Route::put('/workflows/{id}', [\App\Http\Controllers\Api\WorkflowController::class, 'update']);
            Route::delete('/workflows/{id}', [\App\Http\Controllers\Api\WorkflowController::class, 'destroy']);
            Route::post('/workflows/{id}/publish', [\App\Http\Controllers\Api\WorkflowController::class, 'publish']);
            Route::post('/workflows/{id}/archive', [\App\Http\Controllers\Api\WorkflowController::class, 'archive']);
        });

        // Automation Rules (Protected by automation.manage)
        Route::middleware('permission:automation.manage')->group(function () {
            Route::get('/automation-rules', [\App\Http\Controllers\Api\AutomationRuleController::class, 'index']);
            Route::post('/automation-rules', [\App\Http\Controllers\Api\AutomationRuleController::class, 'store']);
            Route::get('/automation-rules/{id}', [\App\Http\Controllers\Api\AutomationRuleController::class, 'show']);
            Route::put('/automation-rules/{id}', [\App\Http\Controllers\Api\AutomationRuleController::class, 'update']);
            Route::delete('/automation-rules/{id}', [\App\Http\Controllers\Api\AutomationRuleController::class, 'destroy']);
            Route::post('/automation-rules/{id}/toggle', [\App\Http\Controllers\Api\AutomationRuleController::class, 'toggle']);
        });

        // Integrations & Webhooks (Protected by integrations.manage)
        Route::middleware('permission:integrations.manage')->group(function () {
            Route::get('/integrations', [\App\Http\Controllers\Api\IntegrationController::class, 'index']);
            Route::post('/integrations', [\App\Http\Controllers\Api\IntegrationController::class, 'store']);
            Route::get('/integrations/{id}', [\App\Http\Controllers\Api\IntegrationController::class, 'show']);
            Route::put('/integrations/{id}', [\App\Http\Controllers\Api\IntegrationController::class, 'update']);
            Route::delete('/integrations/{id}', [\App\Http\Controllers\Api\IntegrationController::class, 'destroy']);

            Route::get('/webhook-endpoints', [\App\Http\Controllers\Api\WebhookEndpointController::class, 'index']);
            Route::post('/webhook-endpoints', [\App\Http\Controllers\Api\WebhookEndpointController::class, 'store']);
            Route::get('/webhook-endpoints/{id}', [\App\Http\Controllers\Api\WebhookEndpointController::class, 'show']);
            Route::put('/webhook-endpoints/{id}', [\App\Http\Controllers\Api\WebhookEndpointController::class, 'update']);
            Route::delete('/webhook-endpoints/{id}', [\App\Http\Controllers\Api\WebhookEndpointController::class, 'destroy']);
        });

        // Teams & Groups
        Route::get('/teams', [\App\Http\Controllers\Api\TeamController::class, 'index']);
        Route::get('/teams/{id}', [\App\Http\Controllers\Api\TeamController::class, 'show']);
        Route::get('/teams/{id}/members', [\App\Http\Controllers\Api\TeamController::class, 'members']);
        Route::middleware('permission:roles.manage,departments.manage')->group(function () {
            Route::post('/teams', [\App\Http\Controllers\Api\TeamController::class, 'store']);
            Route::put('/teams/{id}', [\App\Http\Controllers\Api\TeamController::class, 'update']);
            Route::delete('/teams/{id}', [\App\Http\Controllers\Api\TeamController::class, 'destroy']);
            Route::post('/teams/{id}/members/{userId}', [\App\Http\Controllers\Api\TeamController::class, 'addMember']);
            Route::delete('/teams/{id}/members/{userId}', [\App\Http\Controllers\Api\TeamController::class, 'removeMember']);
        });

        // Ticket Templates (Shablonlar)
        Route::get('/ticket-templates', [\App\Http\Controllers\Api\TicketTemplateController::class, 'index']);
        // DIQQAT: bu yerda `tickets.create` ISHLATILMAYDI — u har bir oddiy foydalanuvchida
        // bor (zayavka yuborish huquqi), ya'ni guard bo'lolmaydi. Shablonlar global obyekt,
        // shuning uchun faqat xodim/boshqaruv huquqlari.
        Route::middleware('permission:tickets.assign,services.manage,roles.manage')->group(function () {
            Route::post('/ticket-templates', [\App\Http\Controllers\Api\TicketTemplateController::class, 'store']);
            Route::put('/ticket-templates/{id}', [\App\Http\Controllers\Api\TicketTemplateController::class, 'update']);
            Route::delete('/ticket-templates/{id}', [\App\Http\Controllers\Api\TicketTemplateController::class, 'destroy']);
        });

        // Tags
        Route::get('/tags', [\App\Http\Controllers\Api\TagController::class, 'index']);
        Route::get('/tags/{id}', [\App\Http\Controllers\Api\TagController::class, 'show']);
        Route::middleware('permission:tickets.assign,services.manage,roles.manage')->group(function () {
            Route::post('/tags', [\App\Http\Controllers\Api\TagController::class, 'store']);
            Route::put('/tags/{id}', [\App\Http\Controllers\Api\TagController::class, 'update']);
            Route::delete('/tags/{id}', [\App\Http\Controllers\Api\TagController::class, 'destroy']);
        });
    });

    // Telegram Bot Webhook (public, called by Telegram)
    Route::post('/telegram/webhook/{botUsername}', [TelegramWebhookController::class, 'handleWebhook']);

});
