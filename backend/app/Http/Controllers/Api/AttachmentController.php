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
    /** Ruxsat etilgan fayl turlari (stored XSS ombori bo'lishi mumkin turlar taqiqlangan). */
    private const ALLOWED_MIMES = 'jpg,jpeg,png,gif,webp,bmp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,mp3,ogg,wav,mp4,webm,mov';

    public function upload(Request $request, StoreAttachmentService $service): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:'.self::ALLOWED_MIMES.'|max:61440',
            'attachable_type' => ['required', 'string', 'in:'.implode(',', [
                \App\Modules\Ticketing\Infrastructure\Eloquent\Ticket::class,
                \App\Models\User::class,
            ])],
            'attachable_id' => 'required|integer',
            'attachment_type_id' => 'nullable|integer|exists:attachment_types,id',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $safeName = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $disk = 'public';
        $dir = 'attachments/' . date('Y/m/d');
        $storagePath = $dir . '/' . $safeName;

        try {
            Storage::disk($disk)->putFileAs($dir, $file, $safeName);
        } catch (\Throwable $e) {
            $disk = 'local';
            Storage::disk($disk)->putFileAs($dir, $file, $safeName);
        }

        $attachment = $service->execute([
            'organization_id' => \App\Support\CurrentOrg::id($request),
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
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Tizimga kiring'], 401);
        }

        $attachment = Attachment::findOrFail($id);

        // IDOR himoyasi: faqat yuklovchi yoki staff (tickets.delete/view) o'chira oladi
        $isOwner = (int) $attachment->uploaded_by === (int) $user->id;
        $isStaff = $user->isSuperAdmin() || $user->hasPermission('tickets.delete') || $user->hasPermission('tickets.view');

        if (! $isOwner && ! $isStaff) {
            return response()->json(['message' => 'Sizda bu faylni o\'chirish huquqi yo\'q'], 403);
        }

        // Faylni storage'dan ham o'chirish (orphan fayllarning oldini oladi)
        try {
            Storage::disk($attachment->storage_disk ?: 'public')->delete($attachment->storage_path);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ATTACHMENT] Fayl storage\'dan o\'chirilmadi', [
                'id' => $attachment->id,
                'error' => $e->getMessage(),
            ]);
        }

        $attachment->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
