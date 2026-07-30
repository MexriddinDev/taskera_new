<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class TicketCollection extends ResourceCollection
{
    public $collects = TicketResource::class;

    public function toArray(Request $request): array
    {
        return [
            'tasks' => $this->collection,
            'total' => $this->resource->total(),
            'skip' => (int) $request->input('skip', 0),
            'limit' => (int) $request->input('limit', 15),
        ];
    }
}
