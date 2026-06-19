<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List all users.
     */
    public function index(): JsonResponse
    {
        $users = User::orderBy('name')->get();

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:users,username',
            'password' => 'required|string|min:6',
            'role' => 'required|in:kasir,owner',
        ]);

        $user = User::create($validated);

        return response()->json([
            'message' => 'User berhasil dibuat.',
            'user' => $user,
        ], 201);
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:kasir,owner',
            'is_active' => 'boolean',
        ]);

        // Only update password if provided
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Deactivate a user (soft delete by toggling is_active).
     */
    public function destroy(User $user, Request $request): JsonResponse
    {
        // Prevent owner from deactivating themselves
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ], 422);
        }

        $user->update(['is_active' => false]);

        return response()->json([
            'message' => 'User berhasil dinonaktifkan.',
        ]);
    }
}
