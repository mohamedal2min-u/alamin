<?php

namespace App\Actions\FlockNote;

use App\Models\FlockNote;
use Illuminate\Database\Eloquent\Collection;

class ListFlockNotesAction
{
    /**
     * Return all notes for a given flock, newest first.
     *
     * @return Collection<int, FlockNote>
     */
    public function execute(int $farmId, int $flockId): Collection
    {
        return FlockNote::where('farm_id', $farmId)
            ->where('flock_id', $flockId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }
}
