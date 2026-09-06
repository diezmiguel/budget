<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            User::query()->orderBy('name')->get()
        );
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => $request->validated('role'),
            'password' => Hash::make($request->validated('password')),
        ]);

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => $request->validated('role'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        // Prevent an admin from deleting their own account.
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Não pode remover a sua própria conta.',
            ], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Utilizador removido.']);
    }
}
