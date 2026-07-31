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

        \App\Modules\Audit\Domain\Services\AuditLogger::log($request, 'ATTACHMENT_UPLOADED', "Fayl yuklandi: {$originalName}", [
            'actor_user_id' => $request->user()->id,
            'auditable_type' => $validated['attachable_type'],
            'auditable_id' => $validated['attachable_id'],
        ]);

        return response()->json([
            'data' => new AttachmentResource($attachment),
        ], 201);
    }

    public function download(int $id)    {
        $attachment = Attachment::find($id);
        if (!$attachment) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }

        $path = $attachment->storage_path;
        $mime = $attachment->mime_type ?? 'application/octet-stream';

        if (Storage::disk('public')->exists($path)) {
            return response()->file(Storage::disk('public')->path($path), [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . ($attachment->original_name ?? 'file') . '"',
            ]);
        }

        if (Storage::disk('local')->exists($path)) {
            return response()->file(Storage::disk('local')->path($path), [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . ($attachment->original_name ?? 'file') . '"',
            ]);
        }

        try {
            if (Storage::disk('s3')->exists($path)) {
                return redirect(Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(30)));
            }
        } catch (\Throwable $e) {
            // S3 fallback
        }

        return response()->json(['message' => 'File not found on storage'], 404);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $attachment = Attachment::findOrFail($id);

        $attachment->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
