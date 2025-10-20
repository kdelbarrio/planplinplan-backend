<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTypeAlias extends Model
{
    protected $fillable = [
        'event_type_id','source','source_code','source_label','confidence','is_active'
    ];

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    /* Scopes útiles */
    public function scopeSource($q, string $source) {
        return $q->where('source', $source);
    }

    public function scopeCode($q, string $code) {
        return $q->where('source_code', $code);
    }

    public function scopeActive($q) {
        return $q->where('is_active', true);
    }
}
