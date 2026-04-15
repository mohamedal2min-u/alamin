<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FlockNote
 */
class FlockNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'flock_id'   => $this->flock_id,
            'note_type'  => $this->note_type,
            'note_text'  => $this->note_text,
            'entry_date' => $this->entry_date?->toDateString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
