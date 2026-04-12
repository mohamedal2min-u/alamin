<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\ChangePasswordAction;
use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Actions\Auth\MeAction;
use App\Actions\Auth\SubmitRegistrationRequestAction;
use App\Actions\Auth\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SubmitRegistrationRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginAction                    $loginAction,
        private readonly LogoutAction                   $logoutAction,
        private readonly MeAction                       $meAction,
        private readonly UpdateProfileAction            $updateProfileAction,
        private readonly ChangePasswordAction           $changePasswordAction,
        private readonly SubmitRegistrationRequestAction $submitRegistrationAction,
    ) {}

    // ── POST /api/auth/login ──────────────────────────────────────────────────

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->loginAction->execute($request->validated());

            return response()->json($result, 200);
        } catch (\Exception $e) {
            $code = $e->getCode();
            // Ensure the code is a valid HTTP status code (3 digits between 100-599)
            $httpCode = (is_int($code) && $code >= 100 && $code < 600) ? $code : 422;
            
            return response()->json(
                ['message' => $e->getMessage()],
                $httpCode
            );
        }

    }

    // ── POST /api/auth/logout ─────────────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        $this->logoutAction->execute($request->user());

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح'], 200);
    }

    // ── GET /api/auth/me ──────────────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        $user = $this->meAction->execute($request->user());

        return response()->json(['data' => $user], 200);
    }

    // ── PUT /api/auth/me ──────────────────────────────────────────────────────

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->updateProfileAction->execute(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'message' => 'تم تحديث الملف الشخصي بنجاح',
            'data'    => $this->meAction->execute($user),
        ], 200);
    }

    // ── PUT /api/auth/password ────────────────────────────────────────────────

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->changePasswordAction->execute(
            $request->user(),
            $request->validated(),
        );

        return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح'], 200);
    }

    // ── POST /api/auth/register-request ──────────────────────────────────────

    public function registerRequest(SubmitRegistrationRequest $request): JsonResponse
    {
        $this->submitRegistrationAction->execute($request->validated());

        return response()->json([
            'message' => 'تم إرسال طلب التسجيل بنجاح، سيتم التواصل معك قريباً',
        ], 201);
    }
}
