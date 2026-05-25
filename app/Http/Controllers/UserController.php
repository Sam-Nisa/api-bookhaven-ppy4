<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Storage;
use App\Services\ImageKitService;

class UserController extends Controller
{
    protected $imageKit;

    public function __construct(ImageKitService $imageKit)
    {
        $this->imageKit = $imageKit;
    }
    /**
     * List all users.
     */
    public function index()
    {
        $users = User::select('id', 'name', 'email', 'role', 'avatar', 'created_at')->get();

        // Fix avatar URLs
        $users->map(function ($user) {
            $user->avatar = $user->avatar
                ? (filter_var($user->avatar, FILTER_VALIDATE_URL)
                    ? $user->avatar
                    : asset('storage/' . $user->avatar))
                : null;
            return $user;
        });

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Show single user by ID.
     */
    public function show($id)
    {
        $user = User::select('id', 'name', 'email', 'role', 'avatar', 'created_at')->find($id);
    
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    
        // Prepare full avatar URL
        $avatarUrl = $user->avatar
            ? (filter_var($user->avatar, FILTER_VALIDATE_URL)
                ? $user->avatar
                : asset('storage/' . $user->avatar))
            : null;
    
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,      // original value
                'avatar_url' => $avatarUrl,     // full URL for frontend
                'created_at' => $user->created_at,
            ]
        ]);
    }
    

    /**
     * Register new user (default role: user).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'avatar'   => 'nullable', // can be URL or file
        ]);

        // Handle avatar upload or URL
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $upload = $this->imageKit->upload(
                $file->getPathname(),
                time().'_'.$file->getClientOriginalName(),
                '/users/avatars'
            );
            $avatarPath = $upload->result->url;
        } elseif ($request->has('avatar') && filter_var($request->avatar, FILTER_VALIDATE_URL)) {
            $avatarPath = $request->avatar; // keep URL
        }

        $user = User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'password_hash' => Hash::make($validated['password']),
            'role'          => 'user', // default role
            'avatar'        => $avatarPath,
        ]);

        $token = JWTAuth::fromUser($user);

        // Return correct avatar URL
        $user->avatar = $user->avatar
            ? (filter_var($user->avatar, FILTER_VALIDATE_URL)
                ? $user->avatar
                : asset('storage/' . $user->avatar))
            : null;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Update user information (supports avatar upload or URL).
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);
    
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    
        // Validation rules
        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:6',
            'avatar'   => 'sometimes', 
            'role'     => ['sometimes', Rule::in(['user', 'admin', 'author'])],
        ]);
    
        // Hash password if provided
        if (isset($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
            unset($validated['password']);
        }
    
        // Handle avatar
        if ($request->hasFile('avatar')) {
            // Delete old avatar if it's not a URL
            if ($user->avatar && !filter_var($user->avatar, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
    
            $file = $request->file('avatar');
            $upload = $this->imageKit->upload(
                $file->getPathname(),
                time().'_'.$file->getClientOriginalName(),
                '/users/avatars'
            );
            $validated['avatar'] = $upload->result->url;
    
        } elseif ($request->has('avatar') && filter_var($request->avatar, FILTER_VALIDATE_URL)) {
            // If avatar is a URL, just store the URL
            $validated['avatar'] = $request->avatar;
        }
    
        // Update the user
        $user->update($validated);
    
        // Prepare full avatar URL
        $avatarUrl = $user->avatar
            ? (filter_var($user->avatar, FILTER_VALIDATE_URL)
                ? $user->avatar
                : asset('storage/' . $user->avatar))
            : null;
    
        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,      // original value
                'avatar_url' => $avatarUrl,     // full URL for frontend
                'created_at' => $user->created_at,
            ]
        ]);
    }
    
    

    /**
     * Delete a user.
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Delete local avatar file if exists
        if ($user->avatar && !filter_var($user->avatar, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Approve user to author role.
     */
    public function approveToAuthor($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->role === 'author') {
            return response()->json([
                'success' => false,
                'message' => 'User is already an author'
            ], 400);
        }

        $user->role = 'author';
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User approved as author successfully',
            'data' => $user
        ]);
    }
}
