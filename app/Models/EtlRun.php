<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtlRun extends Model
{
    protected $guarded = []; // <- permite create([...])
    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];
}

