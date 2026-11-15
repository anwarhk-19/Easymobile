<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = User::query()->latest();
        if ($request->filled('role')) {
            $q->where('role', $request->input('role'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('q')) {
            $like = '%'.$request->input('q').'%';
            $q->where(fn ($s) => $s->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like));
        }
        $q->withCount(['orders', 'repairRequests']);

        return response()->json($q->paginate(25)->withQueryString());
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['customerProfile.area', 'shop', 'technicianProfile'])->loadCount(['orders', 'repairRequests']);

        return response()->json([
            'user' => $user,
            'recent_orders' => $user->orders()->with('shop:id,name')->latest()->limit(10)->get(),
            'recent_repairs' => $user->repairRequests()->latest()->limit(10)->get(),
        ]);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot suspend yourself.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($user, $data) {
            $user->update(['status' => User::STATUS_SUSPENDED]);
            $user->forceFill(['suspension_reason' => $data['reason']])->save();
            $user->tokens()->delete();
            AuditLogger::log('user.suspended', $user, "Suspended {$user->role} {$user->name}", ['reason' => $data['reason']]);
        });

        return response()->json(['user' => $user]);
    }

    public function activate(User $user): JsonResponse
    {
        $user->update(['status' => User::STATUS_ACTIVE]);
        $user->forceFill(['suspension_reason' => null])->save();
        AuditLogger::log('user.activated', $user, "Reactivated {$user->role} {$user->name}");

        return response()->json(['user' => $user]);
    }

    public function storeStaff(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', Rule::in([Role::ADMIN, Role::SUPPORT])],
            'password' => ['required', Password::min(10)->mixedCase()->numbers()],
        ]);
        $user = User::create($data + ['status' => User::STATUS_ACTIVE]);
        AuditLogger::log('staff.created', $user, "Created {$user->role} account for {$user->email}");

        return response()->json(['user' => $user], 201);
    }
}
