<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenIsValid
{
    protected $database;

    public function __construct()
    {
        $this->database = App::make('firebase.database');
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'No token provided'
            ], 401);
        }

        try {
            $usersRef = $this->database->getReference('users');
            $userSnapshot = $usersRef->orderByChild('token_data/token')->equalTo($token)->getSnapshot();
            $userData = $userSnapshot->getValue();

            if (empty($userData)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid token'
                ], 401);
            }

            $user = reset($userData);

            if (
                !isset($user['token_data']['expires_at']) ||
                $user['token_data']['expires_at'] < now()->timestamp
            ) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token expired'
                ], 401);
            }

            $user['id'] = array_key_first($userData);
            $request->setUserResolver(function () use ($user) {
                return (object) $user;
            });

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 401);
        }
    }
}
