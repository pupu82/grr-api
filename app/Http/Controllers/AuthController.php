<?php

namespace App\Http\Controllers;

use App\Actions\Auth\AttemptLogin;
use App\Actions\Auth\ChangePassword;
use App\Actions\Auth\RegisterUser;
use App\Enums\ErrorCode;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Self-register as an inspector. The response is identical whether or not
     * the employee code is already taken, so it never reveals a code exists.
     */
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $registerUser->handle($request->validated());

        return response()->json([
            'message' => 'Registration received, pending approval.',
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Log in with employee_code and password and issue a Sanctum token.
     */
    public function login(LoginRequest $request, AttemptLogin $attemptLogin): JsonResponse
    {
        $user = $attemptLogin->handle(
            $request->validated('employee_code'),
            $request->validated('password'),
        );

        if ($user === null) {
            return ErrorCode::InvalidCredentials->response();
        }

        if (! $user->status->canLogin()) {
            return ErrorCode::forAccountStatus($user->status)->response();
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->loadMissing('team')),
        ]);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->loadMissing('team'));
    }

    /**
     * Change the password of the authenticated user.
     */
    public function changePassword(ChangePasswordRequest $request, ChangePassword $changePassword): JsonResponse
    {
        $changePassword->handle(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return response()->json([
            'message' => 'Password changed.',
        ]);
    }
}
