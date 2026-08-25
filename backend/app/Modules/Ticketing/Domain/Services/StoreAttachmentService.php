<?php

namespace App\Modules\Ticketing\Domain\Services;

use App\Modules\Ticketing\Infrastructure\Eloquent\Attachment;
use App\Modules\Ticketing\Domain\Events\AttachmentStored;
use Illuminate\Support\Facades\DB;

class StoreAttachmentService
{
    public function execute(array $data): Attachment
    {
        return DB::transaction(function () use ($data) {
            $attachment = new Attachment();
            $attachment->organization_id = $data['organization_id'];
            $attachment->attachable_type = $data['attachable_type'];
            $attachment->attachable_id = $data['attachable_id'];
            $attachment->attachment_type_id = $data['attachment_type_id'] ?? 1;
            $attachment->uploaded_by = $data['uploaded_by'] ?? null;
            $attachment->source_id = $data['source_id'] ?? 1;
            $attachment->storage_disk = $data['storage_disk'] ?? 's3';
            $attachment->storage_path = $data['storage_path'];
            $attachment->original_name = $data['original_name'];
            $attachment->safe_name = $data['safe_name'] ?? $data['original_name'];
            $attachment->mime_type = $data['mime_type'];
            $attachment->size_bytes = $data['size_bytes'];
            $attachment->sha256 = $data['sha256'];
            $attachment->antivirus_status = 'PENDING';
            $attachment->save();

            event(new AttachmentStored($attachment));
            return $attachment;
        });
    }
}
