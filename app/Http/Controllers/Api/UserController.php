<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UserIndexRequest;
use App\Http\Requests\Api\UserRequest;
use App\Http\Requests\Api\UserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(UserIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $users = User::query()
            ->where('business_id', $this->businessId($request))
            ->filter($filters)
            ->orderByRaw("role = 'admin' desc")
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return UserResource::collection($users);
    }

    public function store(UserRequest $request): UserResource
    {
        return new UserResource(User::query()->create([
            ...$request->validated(),
            'business_id' => $this->businessId($request),
            'role' => 'staff',
            'status' => 'active',
        ]));
    }

    public function updateStatus(UserStatusRequest $request, User $user): UserResource
    {
        abort_unless($user->business_id === $this->businessId($request), 404);

        $data = $request->validated();

        if ($request->user()?->is($user) && $data['status'] === 'inactive') {
            throw ValidationException::withMessages([
                'user' => ['You cannot disable your own account.'],
            ]);
        }

        $user->update($data);

        if ($data['status'] === 'inactive') {
            $user->tokens()->delete();
        }

        return new UserResource($user);
    }
}
