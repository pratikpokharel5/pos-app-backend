<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->with('business')
            ->where(function ($query) use ($credentials): void {
                $query
                    ->where('email', $credentials['email'])
                    ->orWhere('phone', $credentials['email']);
            })
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This user account is inactive.'],
            ]);
        }

        if ($user->business?->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This business account is inactive.'],
            ]);
        }

        $abilities = $user->role === 'admin'
            ? ['*']
            : ['counter'];

        $token = $user->createToken('pos-api-token', $abilities)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        return DB::transaction(function () use ($data): JsonResponse {
            $business = Business::query()->create([
                'name' => $data['business_name'],
                'slug' => $this->uniqueBusinessSlug($data['business_name']),
                'phone' => $data['phone'],
                'plan' => 'starter',
                'status' => 'active',
            ]);

            BusinessSetting::query()->create([
                'business_id' => $business->id,
                'tax_enabled' => false,
                'default_tax_rate' => 0,
                'online_payment_enabled' => true,
            ]);

            $user = User::query()->create([
                'business_id' => $business->id,
                'name' => $data['owner_name'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'admin',
                'status' => 'active',
            ]);

            $token = $user->createToken('pos-api-token', ['*'])->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $this->userPayload($user->load('business')),
            ], 201);
        });
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
            'business' => [
                'id' => $user->business->id,
                'name' => $user->business->name,
                'slug' => $user->business->slug,
                'status' => $user->business->status,
            ],
        ];
    }

    private function uniqueBusinessSlug(string $businessName): string
    {
        $baseSlug = Str::slug($businessName) ?: 'business';
        $slug = $baseSlug;
        $counter = 2;

        while (Business::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
