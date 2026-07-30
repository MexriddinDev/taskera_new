<?php

namespace App\Models\Catalog;

use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    protected $table = 'service_requests';

    protected $primaryKey = 'ticket_id';

    public $incrementing = false;

    protected $guarded = ['ticket_id'];

    protected function casts(): array
    {
        return [
            'form_data' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function catalogItem()
    {
        return $this->belongsTo(ServiceCatalogItem::class, 'catalog_item_id');
    }
}
