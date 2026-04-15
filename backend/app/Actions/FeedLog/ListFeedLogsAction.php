<?php

namespace App\Actions\FeedLog;

use App\Models\FlockFeedLog;
use Illuminate\Database\Eloquent\Collection;

class ListFeedLogsAction
{
    /**
     * Return all feed log records for a given flock, newest first.
     *
     * @return Collection<int, FlockFeedLog>
     */
    public function execute(int $farmId, int $flockId): Collection
    {
        return FlockFeedLog::where('farm_id', $farmId)
            ->where('flock_id', $flockId)
            ->with('item:id,name,input_unit')
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }
}
