<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class AdminRegistrationRequestController extends Controller
{
    public function index(): JsonResponse
    {
        $requests = RegistrationRequest::latest()->get();

        return response()->json([
            'data' => $requests
        ]);
    }

    public function approve(RegistrationRequest $registrationRequest): JsonResponse
    {
        if ($registrationRequest->status !== 'pending') {
            return response()->json(['message' => 'الطلب تمت معالجته مسبقاً'], 400);
        }

        DB::transaction(function () use ($registrationRequest) {
            $user = User::firstOrCreate(
                ['whatsapp' => $registrationRequest->whatsapp],
                [
                    'name' => $registrationRequest->name,
                    'email' => $registrationRequest->email,
                    'password' => $registrationRequest->password_hash,
                    'status' => 'active',
                ]
            );

            $farmName = $registrationRequest->farm_name ?? ('مزرعة ' . $registrationRequest->name);
            
            $farm = Farm::create([
                'name' => $farmName,
                'location' => $registrationRequest->location,
                'status' => 'active',
                'admin_user_id' => $user->id,
                'started_at' => now()->toDateString(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            FarmUser::create([
                'farm_id' => $farm->id,
                'user_id' => $user->id,
                'status' => 'active',
                'is_primary' => true,
                'joined_at' => now(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $registrar = app(PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($farm->id);
            $user->assignRole('farm_admin');
            $registrar->setPermissionsTeamId(null);

            $registrationRequest->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        });

        return response()->json(['message' => 'تم قبول الطلب وتجهيز المزرعة بنجاح.']);
    }

    public function reject(Request $request, RegistrationRequest $registrationRequest): JsonResponse
    {
        if ($registrationRequest->status !== 'pending') {
            return response()->json(['message' => 'الطلب تمت معالجته مسبقاً'], 400);
        }

        $request->validate([
            'reason' => 'nullable|string|max:1000'
        ]);

        $registrationRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $request->input('reason'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'تم رفض الطلب بنجاح.']);
    }
}
