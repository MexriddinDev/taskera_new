<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AssetModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'manufacturer' => $this->whenLoaded('manufacturer', fn() => new ManufacturerResource($this->manufacturer)),
            'asset_type_id' => $this->asset_type_id,
            'model_name' => $this->model_name,
            'part_number' => $this->part_number,
            'attributes_schema' => $this->attributes_schema,
        ];
    }
}
