<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UserIndexRequest;
use App\Http\Requests\Api\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(UserIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $users = User::query()
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw("role = 'admin' desc")
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return UserResource::collection($users);
    }

    public function store(UserRequest $request): UserResource
    {
        $data = $request->validated();
        $data['role'] = 'staff';
        $data['status'] = $data['status'] ?? 'active';

        return new UserResource(User::query()->create($data));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        if (
            $request->user()?->is($user) &&
            ($data['status'] ?? $user->status) === 'inactive'
        ) {
            throw ValidationException::withMessages([
                'user' => ['You cannot disable your own account.'],
            ]);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        if (($data['status'] ?? null) === 'inactive') {
            $user->tokens()->delete();
        }

        return new UserResource($user);
    }

    public function destroy(Request $request, User $user): Response
    {
        if ($request->user()?->is($user)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot disable your own account.'],
            ]);
        }

        $user->update(['status' => 'inactive']);
        $user->tokens()->delete();

        return response()->noContent();
    }
}
