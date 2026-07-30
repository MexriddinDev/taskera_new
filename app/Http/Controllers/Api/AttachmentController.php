<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Modules\Ticketing\Domain\Services\StoreAttachmentService;
use App\Modules\Ticketing\Infrastructure\Eloquent\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    public function upload(Request $request, StoreAttachmentService $service): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:51200',
            'attachable_type' => 'required|string',
            'attachable_id' => 'required|integer',
            'attachment_type_id' => 'nullable|integer|exists:attachment_types,id',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $safeName = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $disk = 's3';
        $storagePath = 'attachments/' . date('Y/m/d') . '/' . $safeName;

        Storage::disk($disk)->put($storagePath, file_get_contents($file->getRealPath()));

        $attachment = $service->execute([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'attachable_type' => $validated['attachable_type'],
            'attachable_id' => $validated['attachable_id'],
            'attachment_type_id' => $validated['attachment_type_id'] ?? 1,
            'uploaded_by' => $request->user()->id,
            'source_id' => 1,
            'storage_disk' => $disk,
            'storage_path' => $storagePath,
            'original_name' => $originalName,
            'safe_name' => $safeName,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
        ]);

        return response()->json([
            'data' => new AttachmentResource($attachment),
        ], 201);
    }

    public function download(int $id): JsonResponse
    {
        $attachment = Attachment::findOrFail($id);

        if (!Storage::disk($attachment->storage_disk)->exists($attachment->storage_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->json([
            'url' => Storage::disk($attachment->storage_disk)->temporaryUrl(
                $attachment->storage_path, now()->addMinutes(5)
            ),
            'original_name' => $attachment->original_name,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $attachment = Attachment::findOrFail($id);

        $attachment->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
