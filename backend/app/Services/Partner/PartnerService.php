<?php

namespace App\Services\Partner;

use App\Models\Farm;
use App\Models\Partner;
use App\Models\FarmPartnerShare;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PartnerService
{
    /**
     * Ensure the farm manager has a partner record with the appropriate share.
     * By default, the manager holds 100% minus the sum of other partners' shares.
     *
     * @param int|string $farmId
     * @return Partner
     */
    public function ensureManagerPartnerExists($farmId): Partner
    {
        $farm = Farm::findOrFail($farmId);
        $adminUserId = $farm->admin_user_id;

        if (!$adminUserId) {
            abort(422, "المزرعة لا تملك مديراً حالياً.");
        }

        $adminUser = User::find($adminUserId);

        // 1. Create or get the Partner record for the manager
        $adminPartner = Partner::firstOrCreate(
            ['farm_id' => $farmId, 'user_id' => $adminUserId],
            [
                'name'     => $adminUser->name ?? 'مدير المزرعة',
                'email'    => $adminUser->email,
                'whatsapp' => $adminUser->whatsapp ?? 'system-generated',
                'status'   => 'active',
                'notes'    => 'الشريك التلقائي لمعرف مدير المزرعة (المالك الأساسي)',
                'created_by' => auth()->id() ?? $adminUserId,
            ]
        );

        // 2. Calculate the expected share (100% - others)
        $otherSharesSum = FarmPartnerShare::where('farm_id', $farmId)
            ->where('is_active', true)
            ->where('partner_id', '!=', $adminPartner->id)
            ->sum('share_percent');

        $expectedAdminShare = 100 - floatval($otherSharesSum);
        if ($expectedAdminShare < 0) {
            $expectedAdminShare = 0;
        }

        // 3. Sync the share record
        $adminShareRecord = FarmPartnerShare::where([
            'partner_id' => $adminPartner->id,
            'farm_id'    => $farmId,
            'is_active'  => true,
        ])->first();

        if (!$adminShareRecord) {
            $adminPartner->shares()->create([
                'farm_id'        => $farmId,
                'share_percent'  => $expectedAdminShare,
                'is_active'      => true,
                'effective_from' => now(),
                'created_by'     => auth()->id() ?? $adminUserId,
            ]);
        } elseif ($adminShareRecord->share_percent != $expectedAdminShare) {
            $adminShareRecord->update([
                'share_percent' => $expectedAdminShare,
                'updated_by'    => auth()->id() ?? $adminUserId,
            ]);
        }

        return $adminPartner;
    }

    /**
     * Recalculate and sync the admin share based on all partners.
     * Use this when a partner's share is updated or deleted.
     */
    public function syncManagerShare($farmId): void
    {
        $this->ensureManagerPartnerExists($farmId);
    }
}
