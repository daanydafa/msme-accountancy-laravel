<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private $database;

    public function __construct()
    {
        $this->database = \App\Services\FirebaseService::connect();
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid credentials'], 400);
        }

        try {
            $userSnapshot = $this->database->getReference('users')
                ->orderByChild('email')
                ->equalTo($request->email)
                ->getSnapshot();

            $userData = $userSnapshot->getValue();

            if (!$userData || !Hash::check($request->password, reset($userData)['password'])) {
                return response()->json(['message' => 'Invalid email or password'], 401);
            }

            $user = reset($userData);
            $userId = key($userData);

            $token = bin2hex(random_bytes(40));
            $expiresAt = now()->addDay()->timestamp;
            $tokenData = [
                'token' => $token,
                'expires_at' => $expiresAt,
                'created_at' => now()->timestamp
            ];

            $this->database->getReference("users/{$userId}/token_data")->set($tokenData);

            return response()->json([
                'data' => [
                    'token' => $token,
                    'name' => $user['name'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Authentication failed'], 500);
        }
    }


    public function logout(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $this->database->getReference('users/' . $userId . '/token_data')->remove();

            return response()->json([
                'message' => 'Successfully logged out'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Logout failed'], 500);
        }
    }

    public function check(Request $request)
    {
        try {
            $user = $request->user();

            return response()->json([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Authentication check failed'], 401);
        }
    }

    public function refresh(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $token = bin2hex(random_bytes(40));
            $expiresAt = now()->addDay()->timestamp;

            // Update token in Firebase
            $tokenData = [
                'token' => $token,
                'expires_at' => $expiresAt,
                'created_at' => now()->timestamp
            ];

            $this->database->getReference("users/{$userId}/token_data")->set($tokenData);

            return response()->json([
                'data' => [
                    'token' => $token,
                    'expires_at' => $expiresAt
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Token refresh failed'], 500);
        }
    }
}
