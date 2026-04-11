<?php

namespace App\Actions\Flock;

use App\Models\Flock;
use Illuminate\Support\Facades\DB;

class CreateFlockAction
{
    /**
     * أنشئ فوجاً جديداً بحالة draft ضمن المزرعة.
     *
     * @param  array{name: string, start_date: string, initial_count: int, notes?: string|null}  $data
     *
     * @throws \Exception إذا كان هناك فوج نشط بالفعل وأُريد إنشاء نشط
     */
    public function execute(int $farmId, int $userId, array $data): Flock
    {
        return DB::transaction(function () use ($farmId, $userId, $data): Flock {
            return Flock::create([
                'farm_id'       => $farmId,
                'name'          => $data['name'],
                'status'        => 'draft',
                'start_date'    => $data['start_date'],
                'initial_count' => $data['initial_count'],
                'notes'         => $data['notes'] ?? null,
                'created_by'    => $userId,
                'updated_by'    => $userId,
            ]);
        });
    }
}
