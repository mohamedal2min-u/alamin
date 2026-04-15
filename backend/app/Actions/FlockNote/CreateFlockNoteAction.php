<?php

namespace App\Actions\FlockNote;

use App\Models\Flock;
use App\Models\FlockNote;
use Illuminate\Support\Facades\DB;

class CreateFlockNoteAction
{
    /**
     * @throws \Exception 422 if flock is not active
     */
    public function execute(Flock $flock, int $userId, array $data): FlockNote
    {
        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن إضافة ملاحظة على فوج غير نشط', 422);
        }

        return DB::transaction(function () use ($flock, $userId, $data): FlockNote {
            return FlockNote::create([
                'farm_id'    => $flock->farm_id,
                'flock_id'   => $flock->id,
                'note_type'  => $data['note_type'] ?? 'general',
                'note_text'  => $data['note_text'],
                'entry_date' => $data['entry_date'] ?? now()->toDateString(),
                'worker_id'  => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });
    }
}
