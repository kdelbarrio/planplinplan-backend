<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'last_source_at' => 'datetime',
        'is_canceled'    => 'boolean',
        'visible'        => 'boolean',
        'accessibility_tags'  => 'array',
    ];
}
