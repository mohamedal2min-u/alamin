<?php

namespace App\Actions\WaterLog;

use App\Models\Flock;
use App\Models\FlockWaterLog;
use Illuminate\Support\Facades\DB;

class CreateWaterLogAction
{
    /**
     * @throws \Exception 422 if flock is not active
     */
    public function execute(Flock $flock, int $userId, array $data): FlockWaterLog
    {
        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن تسجيل مياه على فوج غير نشط', 422);
        }

        return DB::transaction(function () use ($flock, $userId, $data): FlockWaterLog {
            return FlockWaterLog::create([
                'farm_id'       => $flock->farm_id,
                'flock_id'      => $flock->id,
                'entry_date'    => $data['entry_date'] ?? now()->toDateString(),
                'quantity'      => $data['quantity'] ?? null,
                'unit_label'    => $data['unit_label'] ?? null,
                'notes'         => $data['notes'] ?? null,
                'worker_id'     => $userId,
                'editable_until' => now()->addMinutes(15),
                'created_by'    => $userId,
                'updated_by'    => $userId,
            ]);
        });
    }
}
