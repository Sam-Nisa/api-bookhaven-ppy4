<?php

// use Illuminate\Support\Facades\Route;
// use Laravel\Socialite\Facades\Socialite;


// Route::get('/', function () {
//     return ['Laravel' => app()->version()];
// });


// Route::get('/auth/google/redirect', function () {
//     return Socialite::driver('google')->redirect();
// });

// Route::get('/auth/google/callback', function () {
//     $user = Socialite::driver('google')->user();

//     dd($user); // test first
// });


use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google/redirect', function () {
    return Socialite::driver('google')->redirect();
});

Route::get('/auth/google/callback', function () {
    try {
        $googleUser = Socialite::driver('google')->user();

        // Check if user exists by email
        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            // User exists - just login
            if (!$user->google_id) {
                $user->google_id = $googleUser->getId();
                $user->save();
            }
        } else {
            // Create new user
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'password_hash' => bcrypt(uniqid()),
                'role' => 'user',
            ]);
        }

        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $redirectUrl = $frontendUrl . "/google-success?token=" . $token;

        return response()->redirectTo($redirectUrl);

    } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        return response()->redirectTo($frontendUrl . "/login?error=invalid_state");
        
    } catch (\Exception $e) {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        return response()->redirectTo($frontendUrl . "/login?error=" . urlencode($e->getMessage()));
    }
});
