<?php

namespace App\Actions\WaterLog;

use App\Models\FlockWaterLog;
use Illuminate\Database\Eloquent\Collection;

class ListWaterLogsAction
{
    /**
     * Return all water log records for a given flock, newest first.
     *
     * @return Collection<int, FlockWaterLog>
     */
    public function execute(int $farmId, int $flockId): Collection
    {
        return FlockWaterLog::where('farm_id', $farmId)
            ->where('flock_id', $flockId)
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }
}
