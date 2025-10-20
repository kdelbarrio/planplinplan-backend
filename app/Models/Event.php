<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    // Solo lo que puede editar el panel (curación)
    protected $fillable = [
        'title_cur',
        'description_cur',
        'venue_name_cur',
        'municipality_cur',
        'territory_cur',
        'age_min',
        'age_max',
        'accessibility_tags',
        'moderation',
        'visible',
    ];

    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'last_source_at' => 'datetime',
        'is_canceled'    => 'boolean',
        'visible'        => 'boolean',
        'accessibility_tags'  => 'array',
    ];
    public function eventType()
    {
        return $this->belongsTo(\App\Models\EventType::class);
    }
}
