<?php

namespace App\Actions\Mortality;

use App\Models\FlockMortality;
use Illuminate\Database\Eloquent\Collection;

class ListMortalitiesAction
{
    /**
     * Return all mortality records for a given flock, newest first.
     *
     * @return Collection<int, FlockMortality>
     */
    public function execute(int $flockId): Collection
    {
        return FlockMortality::where('flock_id', $flockId)
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }
}
