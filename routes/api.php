<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
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
use App\Http\Controllers\Api\BusinessCalendarController;
use App\Http\Controllers\Api\SlaTargetController;
use App\Http\Controllers\Api\TicketSlaController;
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


    // Auth APIs
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        // Executive Dashboard Dynamic APIs
        Route::get('/dashboard/stats', [DashboardApiController::class, 'stats']);
        Route::get('/dashboard/tickets', [DashboardApiController::class, 'tickets']);
        Route::post('/dashboard/quick-ticket', [DashboardApiController::class, 'quickTicket']);
        Route::get('/dashboard/search', [DashboardApiController::class, 'search']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Profile
        Route::get('/users/{id}', [ProfileController::class, 'show'])->where('id', '[0-9]+');

        // Tickets (Web Sites Frontend)
        Route::get('/tickets/stats', [TicketController::class, 'stats']);
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
        Route::get('/attachments/{id}/download', [AttachmentController::class, 'download']);
        Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy']);

        // SLA - Business Calendars
        Route::get('/business-calendars', [BusinessCalendarController::class, 'index']);
        Route::post('/business-calendars', [BusinessCalendarController::class, 'store']);
        Route::get('/business-calendars/{id}', [BusinessCalendarController::class, 'show']);
        Route::put('/business-calendars/{id}', [BusinessCalendarController::class, 'update']);
        Route::delete('/business-calendars/{id}', [BusinessCalendarController::class, 'destroy']);

        // SLA - Targets
        Route::get('/sla-targets', [SlaTargetController::class, 'index']);
        Route::post('/sla-targets', [SlaTargetController::class, 'store']);
        Route::get('/sla-targets/{id}', [SlaTargetController::class, 'show']);
        Route::put('/sla-targets/{id}', [SlaTargetController::class, 'update']);
        Route::delete('/sla-targets/{id}', [SlaTargetController::class, 'destroy']);

        // SLA - Ticket SLA (read-only)
        Route::get('/ticket-slas', [TicketSlaController::class, 'index']);
        Route::get('/ticket-slas/{id}', [TicketSlaController::class, 'show']);
        Route::get('/tickets/{ticketId}/sla', [TicketSlaController::class, 'forTicket']);

        // Notifications
        Route::get('/notification-templates', [NotificationTemplateController::class, 'index']);
        Route::post('/notification-templates', [NotificationTemplateController::class, 'store']);
        Route::get('/notification-templates/{id}', [NotificationTemplateController::class, 'show']);
        Route::put('/notification-templates/{id}', [NotificationTemplateController::class, 'update']);
        Route::delete('/notification-templates/{id}', [NotificationTemplateController::class, 'destroy']);

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

        // Audit Logs
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/{id}', [AuditLogController::class, 'show']);

        // Dynamic Roles & Permissions APIs
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

        // Dynamic Departments & Directory APIs
        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::put('/departments/{id}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

        // Organization / HR APIs
        Route::get('/regions', [\App\Http\Controllers\Api\RegionController::class, 'index']);
        Route::post('/regions', [\App\Http\Controllers\Api\RegionController::class, 'store']);
        Route::get('/regions/{id}', [\App\Http\Controllers\Api\RegionController::class, 'show']);
        Route::put('/regions/{id}', [\App\Http\Controllers\Api\RegionController::class, 'update']);
        Route::delete('/regions/{id}', [\App\Http\Controllers\Api\RegionController::class, 'destroy']);

        Route::get('/branches', [\App\Http\Controllers\Api\BranchController::class, 'index']);
        Route::post('/branches', [\App\Http\Controllers\Api\BranchController::class, 'store']);
        Route::get('/branches/{id}', [\App\Http\Controllers\Api\BranchController::class, 'show']);
        Route::put('/branches/{id}', [\App\Http\Controllers\Api\BranchController::class, 'update']);
        Route::delete('/branches/{id}', [\App\Http\Controllers\Api\BranchController::class, 'destroy']);

        Route::get('/positions', [\App\Http\Controllers\Api\PositionController::class, 'index']);
        Route::post('/positions', [\App\Http\Controllers\Api\PositionController::class, 'store']);
        Route::get('/positions/{id}', [\App\Http\Controllers\Api\PositionController::class, 'show']);
        Route::put('/positions/{id}', [\App\Http\Controllers\Api\PositionController::class, 'update']);
        Route::delete('/positions/{id}', [\App\Http\Controllers\Api\PositionController::class, 'destroy']);

        Route::get('/employees', [\App\Http\Controllers\Api\EmployeeController::class, 'index']);
        Route::post('/employees', [\App\Http\Controllers\Api\EmployeeController::class, 'store']);
        Route::get('/employees/{id}', [\App\Http\Controllers\Api\EmployeeController::class, 'show']);
        Route::put('/employees/{id}', [\App\Http\Controllers\Api\EmployeeController::class, 'update']);
        Route::delete('/employees/{id}', [\App\Http\Controllers\Api\EmployeeController::class, 'destroy']);

        // ITSM Master Data APIs
        Route::get('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
        Route::post('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'store']);
        Route::get('/categories/{id}', [\App\Http\Controllers\Api\CategoryController::class, 'show']);
        Route::put('/categories/{id}', [\App\Http\Controllers\Api\CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [\App\Http\Controllers\Api\CategoryController::class, 'destroy']);

        Route::get('/services', [\App\Http\Controllers\Api\ServiceController::class, 'index']);
        Route::post('/services', [\App\Http\Controllers\Api\ServiceController::class, 'store']);
        Route::get('/services/{id}', [\App\Http\Controllers\Api\ServiceController::class, 'show']);
        Route::put('/services/{id}', [\App\Http\Controllers\Api\ServiceController::class, 'update']);
        Route::delete('/services/{id}', [\App\Http\Controllers\Api\ServiceController::class, 'destroy']);

        Route::get('/sla-policies', [\App\Http\Controllers\Api\SlaPolicyController::class, 'index']);
        Route::post('/sla-policies', [\App\Http\Controllers\Api\SlaPolicyController::class, 'store']);
        Route::get('/sla-policies/{id}', [\App\Http\Controllers\Api\SlaPolicyController::class, 'show']);
        Route::put('/sla-policies/{id}', [\App\Http\Controllers\Api\SlaPolicyController::class, 'update']);
        Route::delete('/sla-policies/{id}', [\App\Http\Controllers\Api\SlaPolicyController::class, 'destroy']);

        Route::get('/service-offerings', [\App\Http\Controllers\Api\ServiceOfferingController::class, 'index']);
        Route::post('/service-offerings', [\App\Http\Controllers\Api\ServiceOfferingController::class, 'store']);
        Route::get('/service-offerings/{id}', [\App\Http\Controllers\Api\ServiceOfferingController::class, 'show']);
        Route::put('/service-offerings/{id}', [\App\Http\Controllers\Api\ServiceOfferingController::class, 'update']);
        Route::delete('/service-offerings/{id}', [\App\Http\Controllers\Api\ServiceOfferingController::class, 'destroy']);

        Route::get('/locations', [\App\Http\Controllers\Api\LocationController::class, 'index']);
        Route::post('/locations', [\App\Http\Controllers\Api\LocationController::class, 'store']);
        Route::get('/locations/{id}', [\App\Http\Controllers\Api\LocationController::class, 'show']);
        Route::put('/locations/{id}', [\App\Http\Controllers\Api\LocationController::class, 'update']);
        Route::delete('/locations/{id}', [\App\Http\Controllers\Api\LocationController::class, 'destroy']);

        Route::get('/resolution-codes', [\App\Http\Controllers\Api\ResolutionCodeController::class, 'index']);
        Route::post('/resolution-codes', [\App\Http\Controllers\Api\ResolutionCodeController::class, 'store']);
        Route::get('/resolution-codes/{id}', [\App\Http\Controllers\Api\ResolutionCodeController::class, 'show']);
        Route::put('/resolution-codes/{id}', [\App\Http\Controllers\Api\ResolutionCodeController::class, 'update']);
        Route::delete('/resolution-codes/{id}', [\App\Http\Controllers\Api\ResolutionCodeController::class, 'destroy']);

        // Asset / CMDB APIs
        Route::get('/manufacturers', [ManufacturerController::class, 'index']);
        Route::post('/manufacturers', [ManufacturerController::class, 'store']);
        Route::get('/manufacturers/{id}', [ManufacturerController::class, 'show']);
        Route::put('/manufacturers/{id}', [ManufacturerController::class, 'update']);
        Route::delete('/manufacturers/{id}', [ManufacturerController::class, 'destroy']);

        Route::get('/asset-models', [AssetModelController::class, 'index']);
        Route::post('/asset-models', [AssetModelController::class, 'store']);
        Route::get('/asset-models/{id}', [AssetModelController::class, 'show']);
        Route::put('/asset-models/{id}', [AssetModelController::class, 'update']);
        Route::delete('/asset-models/{id}', [AssetModelController::class, 'destroy']);

        Route::get('/vendors', [VendorController::class, 'index']);
        Route::post('/vendors', [VendorController::class, 'store']);
        Route::get('/vendors/{id}', [VendorController::class, 'show']);
        Route::put('/vendors/{id}', [VendorController::class, 'update']);
        Route::delete('/vendors/{id}', [VendorController::class, 'destroy']);

        Route::get('/software-products', [SoftwareProductController::class, 'index']);
        Route::post('/software-products', [SoftwareProductController::class, 'store']);
        Route::get('/software-products/{id}', [SoftwareProductController::class, 'show']);
        Route::put('/software-products/{id}', [SoftwareProductController::class, 'update']);
        Route::delete('/software-products/{id}', [SoftwareProductController::class, 'destroy']);

        Route::get('/software-licenses', [SoftwareLicenseController::class, 'index']);
        Route::post('/software-licenses', [SoftwareLicenseController::class, 'store']);
        Route::get('/software-licenses/{id}', [SoftwareLicenseController::class, 'show']);
        Route::put('/software-licenses/{id}', [SoftwareLicenseController::class, 'update']);
        Route::delete('/software-licenses/{id}', [SoftwareLicenseController::class, 'destroy']);

        Route::get('/assets', [AssetApiController::class, 'index']);
        Route::post('/assets', [AssetApiController::class, 'store']);
        Route::get('/assets/{id}', [AssetApiController::class, 'show']);
        Route::put('/assets/{id}', [AssetApiController::class, 'update']);
        Route::delete('/assets/{id}', [AssetApiController::class, 'destroy']);

        // Knowledge & CMDB Asset APIs
        Route::get('/knowledge/articles', [KnowledgeArticleController::class, 'index']);
        Route::post('/knowledge/articles', [KnowledgeArticleController::class, 'store']);
        Route::get('/knowledge/articles/{id}', [KnowledgeArticleController::class, 'show']);
        Route::put('/knowledge/articles/{id}', [KnowledgeArticleController::class, 'update']);
        Route::delete('/knowledge/articles/{id}', [KnowledgeArticleController::class, 'destroy']);
        Route::post('/knowledge/articles/{id}/publish', [KnowledgeArticleController::class, 'publish']);
        Route::post('/knowledge/articles/{id}/archive', [KnowledgeArticleController::class, 'archive']);
        Route::post('/knowledge/articles/{id}/feedback', [KnowledgeArticleController::class, 'feedback']);
        Route::get('/knowledge/search', [KnowledgeArticleController::class, 'search']);
        Route::post('/assets/discover', [AssetController::class, 'discover']);

        // Problem Management
        Route::get('/problems', [\App\Http\Controllers\Api\ProblemController::class, 'index']);
        Route::post('/problems', [\App\Http\Controllers\Api\ProblemController::class, 'store']);
        Route::get('/problems/{id}', [\App\Http\Controllers\Api\ProblemController::class, 'show']);
        Route::put('/problems/{id}', [\App\Http\Controllers\Api\ProblemController::class, 'update']);
        Route::delete('/problems/{id}', [\App\Http\Controllers\Api\ProblemController::class, 'destroy']);
        Route::post('/problems/{id}/link-ticket', [\App\Http\Controllers\Api\ProblemController::class, 'linkTicket']);

        // Change Management
        Route::get('/changes', [\App\Http\Controllers\Api\ChangeController::class, 'index']);
        Route::post('/changes', [\App\Http\Controllers\Api\ChangeController::class, 'store']);
        Route::get('/changes/{id}', [\App\Http\Controllers\Api\ChangeController::class, 'show']);
        Route::put('/changes/{id}', [\App\Http\Controllers\Api\ChangeController::class, 'update']);
        Route::delete('/changes/{id}', [\App\Http\Controllers\Api\ChangeController::class, 'destroy']);
        Route::post('/changes/{id}/approve', [\App\Http\Controllers\Api\ChangeController::class, 'approve']);
        Route::post('/changes/{id}/reject', [\App\Http\Controllers\Api\ChangeController::class, 'reject']);

        // Maintenance Windows
        Route::get('/maintenance-windows', [\App\Http\Controllers\Api\MaintenanceWindowController::class, 'index']);
        Route::post('/maintenance-windows', [\App\Http\Controllers\Api\MaintenanceWindowController::class, 'store']);
        Route::get('/maintenance-windows/{id}', [\App\Http\Controllers\Api\MaintenanceWindowController::class, 'show']);
        Route::put('/maintenance-windows/{id}', [\App\Http\Controllers\Api\MaintenanceWindowController::class, 'update']);
        Route::delete('/maintenance-windows/{id}', [\App\Http\Controllers\Api\MaintenanceWindowController::class, 'destroy']);

        // Service Catalog
        Route::get('/catalog/items', [\App\Http\Controllers\Api\ServiceCatalogItemController::class, 'index']);
        Route::post('/catalog/items', [\App\Http\Controllers\Api\ServiceCatalogItemController::class, 'store']);
        Route::get('/catalog/items/{id}', [\App\Http\Controllers\Api\ServiceCatalogItemController::class, 'show']);
        Route::put('/catalog/items/{id}', [\App\Http\Controllers\Api\ServiceCatalogItemController::class, 'update']);
        Route::delete('/catalog/items/{id}', [\App\Http\Controllers\Api\ServiceCatalogItemController::class, 'destroy']);

        // Service Requests
        Route::get('/service-requests', [\App\Http\Controllers\Api\ServiceRequestController::class, 'index']);
        Route::get('/service-requests/{id}', [\App\Http\Controllers\Api\ServiceRequestController::class, 'show']);

        // Approval Requests
        Route::get('/approval-requests', [\App\Http\Controllers\Api\ApprovalRequestController::class, 'index']);
        Route::get('/approval-requests/{id}', [\App\Http\Controllers\Api\ApprovalRequestController::class, 'show']);
        Route::post('/approval-requests/{id}/approve', [\App\Http\Controllers\Api\ApprovalRequestController::class, 'approve']);
        Route::post('/approval-requests/{id}/reject', [\App\Http\Controllers\Api\ApprovalRequestController::class, 'reject']);

        // Workflows
        Route::get('/workflows', [\App\Http\Controllers\Api\WorkflowController::class, 'index']);
        Route::post('/workflows', [\App\Http\Controllers\Api\WorkflowController::class, 'store']);
        Route::get('/workflows/{id}', [\App\Http\Controllers\Api\WorkflowController::class, 'show']);
        Route::put('/workflows/{id}', [\App\Http\Controllers\Api\WorkflowController::class, 'update']);
        Route::delete('/workflows/{id}', [\App\Http\Controllers\Api\WorkflowController::class, 'destroy']);
        Route::post('/workflows/{id}/publish', [\App\Http\Controllers\Api\WorkflowController::class, 'publish']);
        Route::post('/workflows/{id}/archive', [\App\Http\Controllers\Api\WorkflowController::class, 'archive']);

        // Automation Rules
        Route::get('/automation-rules', [\App\Http\Controllers\Api\AutomationRuleController::class, 'index']);
        Route::post('/automation-rules', [\App\Http\Controllers\Api\AutomationRuleController::class, 'store']);
        Route::get('/automation-rules/{id}', [\App\Http\Controllers\Api\AutomationRuleController::class, 'show']);
        Route::put('/automation-rules/{id}', [\App\Http\Controllers\Api\AutomationRuleController::class, 'update']);
        Route::delete('/automation-rules/{id}', [\App\Http\Controllers\Api\AutomationRuleController::class, 'destroy']);
        Route::post('/automation-rules/{id}/toggle', [\App\Http\Controllers\Api\AutomationRuleController::class, 'toggle']);

        // Integrations
        Route::get('/integrations', [\App\Http\Controllers\Api\IntegrationController::class, 'index']);
        Route::post('/integrations', [\App\Http\Controllers\Api\IntegrationController::class, 'store']);
        Route::get('/integrations/{id}', [\App\Http\Controllers\Api\IntegrationController::class, 'show']);
        Route::put('/integrations/{id}', [\App\Http\Controllers\Api\IntegrationController::class, 'update']);
        Route::delete('/integrations/{id}', [\App\Http\Controllers\Api\IntegrationController::class, 'destroy']);

        // Webhook Endpoints
        Route::get('/webhook-endpoints', [\App\Http\Controllers\Api\WebhookEndpointController::class, 'index']);
        Route::post('/webhook-endpoints', [\App\Http\Controllers\Api\WebhookEndpointController::class, 'store']);
        Route::get('/webhook-endpoints/{id}', [\App\Http\Controllers\Api\WebhookEndpointController::class, 'show']);
        Route::put('/webhook-endpoints/{id}', [\App\Http\Controllers\Api\WebhookEndpointController::class, 'update']);
        Route::delete('/webhook-endpoints/{id}', [\App\Http\Controllers\Api\WebhookEndpointController::class, 'destroy']);

        // Teams
        Route::get('/teams', [\App\Http\Controllers\Api\TeamController::class, 'index']);
        Route::post('/teams', [\App\Http\Controllers\Api\TeamController::class, 'store']);
        Route::get('/teams/{id}', [\App\Http\Controllers\Api\TeamController::class, 'show']);
        Route::put('/teams/{id}', [\App\Http\Controllers\Api\TeamController::class, 'update']);
        Route::delete('/teams/{id}', [\App\Http\Controllers\Api\TeamController::class, 'destroy']);
        Route::get('/teams/{id}/members', [\App\Http\Controllers\Api\TeamController::class, 'members']);
        Route::post('/teams/{id}/members/{userId}', [\App\Http\Controllers\Api\TeamController::class, 'addMember']);
        Route::delete('/teams/{id}/members/{userId}', [\App\Http\Controllers\Api\TeamController::class, 'removeMember']);

        // Tags
        Route::get('/tags', [\App\Http\Controllers\Api\TagController::class, 'index']);
        Route::post('/tags', [\App\Http\Controllers\Api\TagController::class, 'store']);
        Route::get('/tags/{id}', [\App\Http\Controllers\Api\TagController::class, 'show']);
        Route::put('/tags/{id}', [\App\Http\Controllers\Api\TagController::class, 'update']);
        Route::delete('/tags/{id}', [\App\Http\Controllers\Api\TagController::class, 'destroy']);
    });

    // Telegram Bot Webhook (public, called by Telegram)
    Route::post('/telegram/webhook/{botUsername}', [TelegramWebhookController::class, 'handleWebhook']);

});
