<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use App\Models\FarmPartnerShare;
use App\Models\User;
use App\Models\Farm;
use App\Models\FarmUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class PartnerController extends Controller
{
    /**
     * Display a listing of partners for the current farm.
     */
    public function index(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        $partners = Partner::where('farm_id', $farmId)
            ->with(['shares' => function($query) {
                $query->where('is_active', true);
            }, 'user:id,name,email,whatsapp'])
            ->orderBy('name')
            ->get();

        return PartnerResource::collection($partners);
    }

    /**
     * Store a newly created partner in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $creatorUserId = $request->user()->id;

        $validated = $request->validate([
            'name'          => 'required|string|max:150',
            'email'         => 'nullable|email|max:190',
            'whatsapp'      => 'required|string|max:30',
            'password'      => 'required|string|min:8',
            'status'        => 'nullable|string|in:active,inactive',
            'notes'         => 'nullable|string',
            'share_percent' => 'nullable|numeric|min:0|max:100',
        ], [
            'password.required' => 'كلمة المرور مطلوبة عند إنشاء حساب شريك جديد',
            'password.min'      => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
        ]);

        $sharePercent = $validated['share_percent'] ?? 0;

        return DB::transaction(function () use ($validated, $farmId, $creatorUserId, $sharePercent) {
            // Override status based on share percent
            $validated['status'] = $sharePercent <= 0 ? 'inactive' : 'active';

            // 1. Ensure admin partner exists and has enough share
            $adminPartner = $this->ensureAdminPartnerExists($farmId);
            $adminShareRecord = $adminPartner->shares()->where('is_active', true)->first();
            $adminShare = $adminShareRecord ? $adminShareRecord->share_percent : 0;

            if ($sharePercent > 0 && \App\Models\Flock::where('farm_id', $farmId)->where('status', 'active')->exists()) {
                abort(422, 'لا يمكن تعديل هيكل الملكية (إضافة حصة لشريك) أثناء وجود فوج نشط.');
            }

            if ($sharePercent > $adminShare) {
                abort(422, "حصة مدير المزرعة ($adminShare%) لا تكفي لإعطاء هذه النسبة.");
            }

            // 2. Check or Create User (using whatsapp as identifier)
            $user = User::firstWhere('whatsapp', $validated['whatsapp']);
            if (!$user) {
                $user = clone User::create([
                    'name'     => $validated['name'],
                    'email'    => $validated['email'] ?? null,
                    'whatsapp' => $validated['whatsapp'],
                    'password' => Hash::make($validated['password']),
                    'status'   => $validated['status'],
                ]);
            } else {
                // Sync status if user already exists
                $user->update(['status' => $validated['status']]);
            }

            // 3. Link user to farm
            FarmUser::updateOrCreate(
                ['farm_id' => $farmId, 'user_id' => $user->id],
                [
                    'status' => $validated['status'],
                    'joined_at' => now(),
                    'created_by' => $creatorUserId,
                    'updated_by' => $creatorUserId,
                ]
            );

            // 4. Assign role globally to Spatie
            /** @var PermissionRegistrar $registrar */
            $registrar = app(PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($farmId);
            if (!$user->hasRole('partner')) {
                $user->assignRole('partner');
            }
            $registrar->setPermissionsTeamId(null);

            // 5. Create Partner Record
            $partner = Partner::create([
                'farm_id' => $farmId,
                'user_id' => $user->id,
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'whatsapp' => $validated['whatsapp'],
                'status' => $validated['status'] ?? 'active',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $creatorUserId,
            ]);

            // 6. Manage Shares
            if ($sharePercent > 0) {
                // deduct from admin
                $newAdminShare = $adminShare - $sharePercent;
                $adminShareRecord->update(['share_percent' => $newAdminShare, 'updated_by' => $creatorUserId]);

                // add to new partner
                $partner->shares()->create([
                    'farm_id' => $farmId,
                    'share_percent' => $sharePercent,
                    'is_active' => true,
                    'effective_from' => now(),
                    'created_by' => $creatorUserId,
                ]);
            }

            $partner->load(['shares' => fn ($q) => $q->where('is_active', true), 'user:id,name,email,whatsapp']);
            return (new PartnerResource($partner))->response()->setStatusCode(201);
        });
    }

    /**
     * Update the specified partner in storage.
     */
    public function update(Request $request, Partner $partner): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $creatorUserId = $request->user()->id;
        
        if ($partner->farm_id != $farmId) {
            abort(403, 'غير مصرح بالوصول إلى هذا المورد');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:190',
            'whatsapp' => 'required|string|max:30',
            'password' => 'nullable|string|min:8', // optional update
            'status' => 'required|string|in:active,inactive',
            'notes' => 'nullable|string',
            'share_percent' => 'nullable|numeric|min:0|max:100', // allow share updates
        ]);

        return DB::transaction(function () use ($partner, $validated, $farmId, $creatorUserId) {
            // Override status based on share percent
            if (isset($validated['share_percent'])) {
                $validated['status'] = floatval($validated['share_percent']) <= 0 ? 'inactive' : 'active';
            }

            // Update Partner User
            if ($partner->user_id) {
                $userUpdate = [
                    'name' => $validated['name'],
                    'whatsapp' => $validated['whatsapp'],
                    'status' => $validated['status'] // Sync status to user too
                ];
                if (!empty($validated['password'])) {
                    $userUpdate['password'] = Hash::make($validated['password']);
                }
                if (!empty($validated['email'])) {
                    $userUpdate['email'] = $validated['email'];
                }
                User::where('id', $partner->user_id)->update($userUpdate);
            }

            // Handle Share Updates
            if (isset($validated['share_percent'])) {
                $newShare = floatval($validated['share_percent']);
                $partnerShareRecord = $partner->shares()->where('is_active', true)->first();
                $currentShare = $partnerShareRecord ? floatval($partnerShareRecord->share_percent) : 0;
                
                $shareDifference = $newShare - $currentShare; // if positive, we need more from admin

                if ($shareDifference != 0) {
                    if (\App\Models\Flock::where('farm_id', $farmId)->where('status', 'active')->exists()) {
                        abort(422, 'لا يمكن تعديل هيكل الملكية (تعديل الحصص) أثناء وجود فوج نشط.');
                    }
                    $adminPartner = $this->ensureAdminPartnerExists($farmId);
                    
                    // Don't modify if this IS the admin
                    if ($adminPartner->id !== $partner->id) {
                        $adminShareRecord = $adminPartner->shares()->where('is_active', true)->first();
                        $adminShare = floatval($adminShareRecord->share_percent);
                        
                        if ($adminShare - $shareDifference < 0) {
                            abort(422, "تعديل الحصة يتجاوز الحصة المتبقية لمدير المزرعة (" . $adminShare . "%).");
                        }
                        
                        // Deduct/Add from admin
                        $adminShareRecord->update([
                            'share_percent' => $adminShare - $shareDifference,
                            'updated_by' => $creatorUserId
                        ]);
                        
                        // Update partner share
                        if ($partnerShareRecord) {
                            $partnerShareRecord->update([
                                'share_percent' => $newShare,
                                'updated_by' => $creatorUserId
                            ]);
                        } else if ($newShare > 0) {
                            $partner->shares()->create([
                                'farm_id' => $farmId,
                                'share_percent' => $newShare,
                                'is_active' => true,
                                'effective_from' => now(),
                                'created_by' => $creatorUserId,
                            ]);
                        }
                    }
                }
            }

            // Update Partner Record
            $partner->update([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'whatsapp' => $validated['whatsapp'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'updated_by' => $creatorUserId,
            ]);

            $partner->load(['shares' => fn ($q) => $q->where('is_active', true), 'user:id,name,email,whatsapp']);
            return new PartnerResource($partner);
        });
    }

    /**
     * Remove the specified partner from storage (Soft delete/Deactivate + Share Reversion).
     */
    public function destroy(Request $request, Partner $partner): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $creatorUserId = $request->user()->id;

        if ($partner->farm_id != $farmId) {
            abort(403, 'غير مصرح بالوصول إلى هذا المورد');
        }

        return DB::transaction(function () use ($partner, $farmId, $creatorUserId) {
            $adminPartner = $this->ensureAdminPartnerExists($farmId);

            if ($partner->id === $adminPartner->id) {
                abort(422, "لا يمكن حذف شريك مدير المزرعة.");
            }

            // Revert share to admin
            $partnerShareRecord = $partner->shares()->where('is_active', true)->first();
            if ($partnerShareRecord && $partnerShareRecord->share_percent > 0) {
                if (\App\Models\Flock::where('farm_id', $farmId)->where('status', 'active')->exists()) {
                    abort(422, 'لا يمكن حذف شريك (لديه حصة نشطة) أثناء وجود فوج نشط. يرجى إغلاق الفوج أولاً.');
                }
                
                $adminShareRecord = $adminPartner->shares()->where('is_active', true)->first();
                $adminShareRecord->update([
                    'share_percent' => floatval($adminShareRecord->share_percent) + floatval($partnerShareRecord->share_percent),
                    'updated_by' => $creatorUserId
                ]);
                
                // Deal with partner's share record
                if ($partnerShareRecord) {
                    $partnerShareRecord->delete(); // Delete instead of just deactivate to clean up if deleting partner
                }
            }

            // Remove user from farm access or role? 
            // Better to just deactivate partner 
            $partner->update(['status' => 'inactive', 'updated_by' => $creatorUserId]);

            if (!$partner->transactions()->exists()) {
                // If we are deleting the partner, delete the shares definitively
                FarmPartnerShare::where('partner_id', $partner->id)->delete();
                $partner->delete();
            }

            return response()->json(['message' => 'Partner removed and shares reverted to admin']);
        });
    }

    /**
     * Ensure the farm admin has a partner record with the base share.
     */
    private function ensureAdminPartnerExists($farmId)
    {
        $farm = Farm::findOrFail($farmId);
        $adminUserId = $farm->admin_user_id;

        if (!$adminUserId) {
            abort(422, "المزرعة لا تملك مديراً حالياً.");
        }

        $adminUser = User::find($adminUserId);

        $adminPartner = Partner::firstOrCreate(
            ['farm_id' => $farmId, 'user_id' => $adminUserId],
            [
                'name' => $adminUser->name ?? 'مدير المزرعة',
                'email' => $adminUser->email,
                'whatsapp' => $adminUser->whatsapp ?? 'system-generated',
                'status' => 'active',
                'notes' => 'الشريك التلقائي لمعرف مدير المزرعة',
            ]
        );

        // Calculate missing share logic (Ensure total is 100%)
        // Get sum of all active shares EXCEPT admin's
        $otherSharesSum = FarmPartnerShare::where('farm_id', $farmId)
            ->where('is_active', true)
            ->where('partner_id', '!=', $adminPartner->id)
            ->sum('share_percent');

        $expectedAdminShare = 100 - floatval($otherSharesSum);
        if ($expectedAdminShare < 0) {
            $expectedAdminShare = 0; // fallback but theoretically invalid
        }

        $adminShareRecord = FarmPartnerShare::firstWhere([
            'partner_id' => $adminPartner->id,
            'farm_id' => $farmId,
            'is_active' => true,
        ]);

        if (!$adminShareRecord) {
            $adminPartner->shares()->create([
                'farm_id' => $farmId,
                'share_percent' => $expectedAdminShare,
                'is_active' => true,
                'effective_from' => now(),
            ]);
        } else if ($adminShareRecord->share_percent != $expectedAdminShare && $expectedAdminShare >= 0) {
            // Self-correct Admin Share if it drifted
            $adminShareRecord->update([
                'share_percent' => $expectedAdminShare
            ]);
        }

        return $adminPartner;
    }
}
