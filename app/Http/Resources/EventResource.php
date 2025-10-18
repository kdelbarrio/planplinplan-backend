<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Prioriza campos curados sobre los de origen
        $title        = $this->title_cur        ?: $this->title_src;
        $description  = $this->description_cur  ?: $this->description_src;
        $venue        = $this->venue_name_cur   ?: $this->venue_name_src;
        $municipality = $this->municipality_cur ?: $this->municipality_src;
        $territory    = $this->territory_cur    ?: $this->territory_src;

        // Fechas en Europe/Madrid → ISO 8601
        $toIso = function ($dt) {
            if (!$dt) return null;
            // $dt puede ser Carbon (cast) o string; manejamos ambos
            $c = is_object($dt) && method_exists($dt, 'clone') ? $dt->clone() : \Illuminate\Support\Carbon::parse($dt);
            return $c->timezone('Europe/Madrid')->toIso8601String();
        };

        return [
            'id'            => $this->id,
            'title'         => $title,
            'description'   => $description,
            'starts_at'     => $toIso($this->starts_at),
            'ends_at'       => $toIso($this->ends_at),
            'venue'         => $venue,
            'municipality'  => $municipality,
            'territory'     => $territory,
            'image_url'     => $this->image_url,
            'source_url'    => $this->source_url,

            // metadatos útiles
            'visible'       => (bool) $this->visible,
            'is_canceled'   => (bool) $this->is_canceled,
            'moderation'    => $this->moderation,
            'source'        => $this->source,
            'source_id'     => $this->source_id,
            'last_source_at'=> $toIso($this->last_source_at),
            'created_at'    => $toIso($this->created_at),
            'updated_at'    => $toIso($this->updated_at),
        ];
    }
}
