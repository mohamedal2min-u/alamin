<?php

namespace App\Actions\Flock;

use App\Models\Flock;

class ShowFlockAction
{
    /**
     * تحقق من أن الفوج ينتمي للمزرعة المحددة ثم أعده.
     *
     * @throws \Exception 404 إذا لم يُوجد الفوج في هذه المزرعة
     */
    public function execute(int $farmId, int $flockId): Flock
    {
        $flock = Flock::where('id', $flockId)
            ->where('farm_id', $farmId)
            ->first();

        if (! $flock) {
            throw new \Exception('الفوج غير موجود', 404);
        }

        return $flock;
    }
}
