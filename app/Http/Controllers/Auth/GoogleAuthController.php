<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Start Google OAuth
     *
     * Returns the Google consent-screen URL. The SPA should navigate the browser to `url`.
     *
     * @unauthenticated
     */
    public function redirectToGoogle(): JsonResponse
    {
        $url = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * Complete Google OAuth and issue a token
     *
     * Exchanges the Google `code` for a profile and signs in **only** pre-provisioned users
     * (created by a Superadmin). Any previous Sanctum tokens for the user are revoked and a
     * new Bearer token is returned. Responds `403` when the email is not whitelisted and `422`
     * when Google rejects the code.
     *
     * @unauthenticated
     */
    public function handleGoogleCallback(Request $request): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to authenticate with Google.',
                'error' => $e->getMessage(),
            ], 422);
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            return response()->json([
                'message' => 'Access denied',
            ], 403);
        }

        $user->update([
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'name' => $googleUser->getName(),
        ]);

        $user->tokens()->delete();
        $token = $user->createToken('katrium_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'roles' => $user->roles_list,
                'permissions' => $user->permissions_list,
            ],
        ]);
    }

    /**
     * Get the current user
     *
     * Roles plus the fully-resolved permission list (role permissions + direct overrides).
     * The frontend uses this to drive route guards and UI visibility.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'roles' => $user->roles_list,
            'permissions' => $user->permissions_list,
        ]);
    }

    /**
     * Sign out
     *
     * Revokes the Bearer token used for this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}