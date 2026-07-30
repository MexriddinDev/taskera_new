<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LookupResource;
use App\Models\Reference\ArticleType;
use App\Models\Reference\AssetStatus;
use App\Models\Reference\AssetType;
use App\Models\Reference\AttachmentType;
use App\Models\Reference\CommentSource;
use App\Models\Reference\CommentType;
use App\Models\Reference\EmploymentStatus;
use App\Models\Reference\IntegrationType;
use App\Models\Reference\Locale;
use App\Models\Reference\NotificationChannel;
use App\Models\Reference\RelationshipType;
use App\Models\Reference\TicketPriority;
use App\Models\Reference\TicketSource;
use App\Models\Reference\TicketStatus;
use App\Models\Reference\TicketStatusTransition;
use App\Models\Reference\Timezone;
use App\Models\Reference\WorkflowEntityType;
use Illuminate\Http\JsonResponse;

class ReferenceController extends Controller
{
    public function locales(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(Locale::where('is_active', true)->get())
        );
    }

    public function timezones(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(Timezone::where('is_active', true)->get())
        );
    }

    public function employmentStatuses(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(EmploymentStatus::where('is_active', true)->get())
        );
    }

    public function ticketStatuses(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(TicketStatus::where('is_active', true)->orderBy('sort_order')->get())
        );
    }

    public function ticketStatusTransitions(): JsonResponse
    {
        return response()->json(
            TicketStatusTransition::where('is_active', true)->get()
        );
    }

    public function ticketPriorities(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(TicketPriority::where('is_active', true)->orderBy('weight')->get())
        );
    }

    public function ticketSources(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(TicketSource::where('is_active', true)->get())
        );
    }

    public function commentTypes(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(CommentType::where('is_active', true)->get())
        );
    }

    public function commentSources(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(CommentSource::where('is_active', true)->get())
        );
    }

    public function attachmentTypes(): JsonResponse
    {
        return response()->json(
            AttachmentType::where('is_active', true)->get()
        );
    }

    public function notificationChannels(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(NotificationChannel::where('is_active', true)->get())
        );
    }

    public function assetTypes(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(AssetType::where('is_active', true)->orderBy('sort_order')->get())
        );
    }

    public function assetStatuses(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(AssetStatus::where('is_active', true)->orderBy('sort_order')->get())
        );
    }

    public function relationshipTypes(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(RelationshipType::where('is_active', true)->orderBy('sort_order')->get())
        );
    }

    public function articleTypes(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(ArticleType::where('is_active', true)->get())
        );
    }

    public function workflowEntityTypes(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(WorkflowEntityType::where('is_active', true)->get())
        );
    }

    public function integrationTypes(): JsonResponse
    {
        return response()->json(
            LookupResource::collection(IntegrationType::where('is_active', true)->get())
        );
    }
}
