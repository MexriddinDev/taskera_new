<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'asset_tag' => $this->asset_tag,
            'serial_number' => $this->serial_number,
            'hostname' => $this->hostname,
            'asset_type_id' => $this->asset_type_id,
            'status_id' => $this->status_id,
            'model' => $this->whenLoaded('model', fn() => new AssetModelResource($this->model)),
            'owner' => $this->whenLoaded('owner', fn() => new EmployeeResource($this->owner)),
            'custodian' => $this->whenLoaded('custodian', fn() => new EmployeeResource($this->custodian)),
            'department' => $this->whenLoaded('department', fn() => new DepartmentResource($this->department)),
            'branch' => $this->whenLoaded('branch', fn() => new BranchResource($this->branch)),
            'vendor' => $this->whenLoaded('vendor', fn() => new VendorResource($this->vendor)),
            'purchase_date' => $this->purchase_date,
            'warranty_end_date' => $this->warranty_end_date,
            'installed_at' => $this->installed_at,
            'retired_at' => $this->retired_at,
            'ip_addresses' => $this->ip_addresses,
            'mac_addresses' => $this->mac_addresses,
            'os_name' => $this->os_name,
            'os_version' => $this->os_version,
            'source_system' => $this->source_system,
            'external_id' => $this->external_id,
            'last_discovered_at' => $this->last_discovered_at,
            'attributes' => $this->attributes,
            'lock_version' => $this->lock_version,
        ];
    }
}
