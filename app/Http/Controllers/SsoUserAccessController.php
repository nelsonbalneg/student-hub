<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class SsoUserAccessController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Authenticate using the OAuth client credentials signature
        $clientId = $request->header('X-SSO-Client-ID');
        $timestamp = $request->header('X-SSO-Timestamp');
        $signature = $request->header('X-SSO-Signature');

        if (!$clientId || !$timestamp || !$signature) {
            return response()->json(['message' => 'Missing signature headers.'], 401);
        }

        // Verify timestamp within 5 minutes to prevent replay attacks
        if (abs(time() - $timestamp) > 300) {
            return response()->json(['message' => 'Request expired.'], 401);
        }

        // Retrieve configured client ID and secret
        $configuredClientId = config('services.sso.client_id');
        $configuredClientSecret = config('services.sso.client_secret');

        if (!$configuredClientId || !$configuredClientSecret) {
            return response()->json(['message' => 'SSO client is not configured on this remote server.'], 500);
        }

        if (strtolower($clientId) !== strtolower($configuredClientId)) {
            return response()->json(['message' => 'Invalid Client ID.'], 401);
        }

        // Verify HMAC signature using configured client_secret
        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $clientId, $configuredClientSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Invalid signature verification.'], 401);
        }

        // 2. Fetch the user details and access
        $email = $request->query('email');
        if (blank($email)) {
            return response()->json(['message' => 'Email parameter is required.'], 400);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'roles' => [],
                'permissions' => [],
                'message' => 'User not found in client application database.'
            ]);
        }

        // Retrieve roles and permissions
        $roles = $user->roles->pluck('name')->all();
        $permissions = $user->permissionNames();

        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }
}
