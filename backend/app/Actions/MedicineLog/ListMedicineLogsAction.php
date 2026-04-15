<?php

namespace App\Actions\MedicineLog;

use App\Models\FlockMedicine;
use Illuminate\Database\Eloquent\Collection;

class ListMedicineLogsAction
{
    /**
     * Return all medicine log records for a given flock, newest first.
     *
     * @return Collection<int, FlockMedicine>
     */
    public function execute(int $farmId, int $flockId): Collection
    {
        return FlockMedicine::where('farm_id', $farmId)
            ->where('flock_id', $flockId)
            ->with('item:id,name,input_unit')
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }
}
