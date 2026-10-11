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

        // 3. Sync the share record (locked: every caller runs inside a DB transaction, so this
        // serializes concurrent callers touching the same admin share row instead of racing).
        $adminShareRecord = FarmPartnerShare::where([
            'partner_id' => $adminPartner->id,
            'farm_id'    => $farmId,
            'is_active'  => true,
        ])->lockForUpdate()->first();

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

    /**
     * نسب الشراكة تُقفل ما دام في المزرعة فوج مفتوح (نشط) — لا يعدّلها أحد، حتى مدير النظام.
     * تُعدَّل فقط بين الأفواج (بعد إغلاق الفوج الحالي وقبل بدء التالي).
     */
    public function assertSharesEditable(int $farmId): void
    {
        if (\App\Models\Flock::where('farm_id', $farmId)->where('status', 'active')->exists()) {
            abort(422, 'نسب الشراكة مقفلة: يوجد فوج مفتوح. لا يمكن تعديلها إلا بعد إغلاق الفوج الحالي.');
        }
    }

    /**
     * يحدد حصة شريك (غير المدير) ويعدّل حصة المدير تلقائياً (100% - مجموع الآخرين)،
     * ويسجّل التغيير في سجل الحصص. يجب استدعاؤها داخل DB::transaction والشريك مقفول.
     *
     * @return array{old: float, new: float}
     */
    public function setShare(int $farmId, Partner $partner, float $newShare, int $userId): array
    {
        $partnerShareRecord = $partner->shares()->where('is_active', true)->lockForUpdate()->first();
        $currentShare       = $partnerShareRecord ? (float) $partnerShareRecord->share_percent : 0.0;
        $shareDifference    = $newShare - $currentShare; // موجب = نحتاج المزيد من حصة المدير

        if ($shareDifference == 0.0) {
            return ['old' => $currentShare, 'new' => $currentShare];
        }

        $this->assertSharesEditable($farmId);

        $adminPartner = $this->ensureManagerPartnerExists($farmId);

        // حصة المدير تُحسب تلقائياً ولا تُحدَّد مباشرة
        if ($adminPartner->id === $partner->id) {
            return ['old' => $currentShare, 'new' => $currentShare];
        }

        $adminShareRecord = $adminPartner->shares()->where('is_active', true)->lockForUpdate()->first();
        $adminShare       = (float) $adminShareRecord->share_percent;

        if ($adminShare - $shareDifference < 0) {
            abort(422, 'تعديل الحصة يتجاوز الحصة المتبقية لمدير المزرعة ('.$adminShare.'%).');
        }

        $adminShareRecord->update([
            'share_percent' => $adminShare - $shareDifference,
            'updated_by'    => $userId,
        ]);

        if ($partnerShareRecord) {
            $partnerShareRecord->update(['share_percent' => $newShare, 'updated_by' => $userId]);
        } elseif ($newShare > 0) {
            $partner->shares()->create([
                'farm_id'        => $farmId,
                'share_percent'  => $newShare,
                'is_active'      => true,
                'effective_from' => now(),
                'created_by'     => $userId,
            ]);
        }

        $this->syncManagerShare($farmId);

        \App\Models\PartnerShareAuditLog::create([
            'farm_id'     => $farmId,
            'partner_id'  => $partner->id,
            'user_id'     => $userId,
            'old_percent' => $partnerShareRecord ? $currentShare : null,
            'new_percent' => $newShare,
        ]);

        return ['old' => $currentShare, 'new' => $newShare];
    }
}
