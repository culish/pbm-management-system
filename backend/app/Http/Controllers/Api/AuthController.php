<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const TOKEN_LIFETIME_HOURS = 8;

    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $user = User::with('role')->where('email', $email)->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            Audit::record('auth.login_failed', null, ['email' => $email]);

            throw ValidationException::withMessages([
                'email' => ['Email atau kata sandi salah.'],
            ]);
        }

        if (! $user->is_active) {
            Audit::record('auth.login_blocked', $user, [], $user->id);

            return response()->json(['message' => 'Akun dinonaktifkan. Hubungi administrator.'], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('spa', ['*'], now()->addHours(self::TOKEN_LIFETIME_HOURS));

        Audit::record('auth.login', $user, [], $user->id);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->present($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->present($request->user()->load('role'))]);
    }

    public function logout(Request $request): JsonResponse
    {
        Audit::record('auth.logout', $request->user());

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Anda telah keluar.']);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nip' => $user->nip,
            'role' => $user->role ? ['key' => $user->role->key, 'label' => $user->role->label] : null,
            'permissions' => $user->permissionKeys(),
        ];
    }
}
