<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventType extends Model
{
    protected $fillable = ['slug','name','is_active'];

    public function aliases(): HasMany
    {
        return $this->hasMany(EventTypeAlias::class);
    }

    // Si tu Event tiene FK directa (1:1)
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
