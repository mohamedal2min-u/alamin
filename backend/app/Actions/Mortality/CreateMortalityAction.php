<?php
// backend/app/Actions/Mortality/CreateMortalityAction.php

namespace App\Actions\Mortality;

use App\Models\Flock;
use App\Models\FlockMortality;

class CreateMortalityAction
{
    /**
     * @param  array<string, mixed>  $data  (validated: entry_date, quantity, reason?, notes?)
     *
     * @throws \Exception 422 if flock is not active
     */
    public function execute(Flock $flock, int $userId, array $data): FlockMortality
    {
        if (in_array($flock->status, ['closed', 'cancelled'])) {
            throw new \Exception('لا يمكن تسجيل نفوق على فوج مغلق أو ملغى', 422);
        }

        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن تسجيل نفوق على فوج غير نشط', 422);
        }

        return FlockMortality::create([
            'farm_id'        => $flock->farm_id,
            'flock_id'       => $flock->id,
            'entry_date'     => $data['entry_date'],
            'quantity'       => $data['quantity'],
            'reason'         => $data['reason'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'worker_id'      => $userId,
            'editable_until' => now()->addMinutes(15),
            'created_by'     => $userId,
            'updated_by'     => $userId,
        ]);
    }
}
