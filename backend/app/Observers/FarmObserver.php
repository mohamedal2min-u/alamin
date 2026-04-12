<?php

namespace App\Observers;

use App\Models\Farm;
use App\Models\Warehouse;

class FarmObserver
{
    /**
     * عند إنشاء أي مزرعة جديدة — أنشئ مستودعاً رئيسياً تلقائياً
     */
    public function created(Farm $farm): void
    {
        Warehouse::firstOrCreate(
            ['farm_id' => $farm->id, 'name' => 'المستودع الرئيسي'],
            [
                'is_active'  => true,
                'created_by' => $farm->created_by,
                'updated_by' => $farm->created_by,
            ]
        );
    }
}
