<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = Phone::normalize($request->input('phone'))
            ?? throw ValidationException::withMessages(['phone' => 'Enter a valid 10-digit Indian mobile number.']);

        $existing = User::withTrashed()->where('phone', $phone)->first();
        if ($existing && $existing->isStaff()) {
            throw ValidationException::withMessages(['phone' => 'Staff accounts must sign in with email and password.']);
        }

        $result = $this->otp->send($phone, $request->ip());

        return response()->json(['message' => 'OTP sent to '.Phone::mask($phone), 'is_registered' => (bool) $existing] + $result);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'digits:6'],
            'name' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in([Role::CUSTOMER, Role::MERCHANT, Role::TECHNICIAN])],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);
        $phone = Phone::normalize($data['phone']) ?? throw ValidationException::withMessages(['phone' => 'Invalid mobile number.']);

        // Check this before consuming the OTP so a new user can retry with their name using the same code.
        if (empty($data['name']) && ! User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['name' => 'Please tell us your name to create an account.']);
        }

        $this->otp->verify($phone, $data['code']);

        $user = DB::transaction(function () use ($phone, $data) {
            $user = User::where('phone', $phone)->first();
            if (! $user) {
                $user = User::create([
                    'name' => $data['name'],
                    'phone' => $phone,
                    'role' => $data['role'] ?? Role::CUSTOMER,
                    'status' => User::STATUS_ACTIVE,
                ]);
                if ($user->role === Role::CUSTOMER) {
                    $user->customerProfile()->create();
                }
            }
            $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now(), 'last_login_at' => now()])->save();

            return $user;
        });

        if (! $user->isActive()) {
            return response()->json(['message' => 'Your account has been suspended. Please contact support.', 'code' => 'account_suspended'], 403);
        }

        $token = $user->createToken($data['device_name'] ?? 'web', [$user->role])->plainTextToken;

        return response()->json(['token' => $token, 'user' => $this->present($user)]);
    }

    /** Staff (admin / support operators) sign in with email + password. */
    public function staffLogin(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $user->isStaff() || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        if (! $user->isActive()) {
            return response()->json(['message' => 'This account is suspended.'], 403);
        }
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['token' => $user->createToken('staff', [$user->role])->plainTextToken, 'user' => $this->present($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->present($request->user())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'area_id' => ['nullable', 'exists:areas,id'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);
        $user->update(['name' => $data['name'], 'email' => $data['email'] ?? $user->email]);
        if ($user->role === Role::CUSTOMER) {
            $user->customerProfile()->updateOrCreate(['user_id' => $user->id], ['area_id' => $data['area_id'] ?? null, 'address' => $data['address'] ?? null]);
        }

        return response()->json(['user' => $this->present($user->fresh())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function present(User $user): array
    {
        $user->loadMissing('customerProfile.area', 'shop:id,merchant_id,name,slug,status,rejection_reason', 'technicianProfile:id,user_id,name,slug,status,rejection_reason');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'permissions' => $user->permissionNames(),
            'profile' => $user->customerProfile,
            'shop' => $user->shop,
            'technician' => $user->technicianProfile,
            'unread_notifications' => $user->unreadNotifications()->count(),
        ];
    }
}
