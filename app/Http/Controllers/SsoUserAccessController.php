<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SsoUserAccessController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Authenticate using the OAuth client credentials signature
        $clientId = $request->header('X-SSO-Client-ID');
        $timestamp = $request->header('X-SSO-Timestamp');
        $signature = $request->header('X-SSO-Signature');

        if (! $clientId || ! $timestamp || ! $signature) {
            return response()->json(['message' => 'Missing signature headers.'], 401);
        }

        // Verify timestamp within 5 minutes to prevent replay attacks
        if (abs(time() - $timestamp) > 300) {
            return response()->json(['message' => 'Request expired.'], 401);
        }

        // Retrieve configured client ID and secret
        $configuredClientId = config('services.sso.client_id');
        $configuredClientSecret = config('services.sso.client_secret');

        if (! $configuredClientId || ! $configuredClientSecret) {
            return response()->json(['message' => 'SSO client is not configured on this remote server.'], 500);
        }

        if (strtolower($clientId) !== strtolower($configuredClientId)) {
            return response()->json(['message' => 'Invalid Client ID.'], 401);
        }

        // Verify HMAC signature using configured client_secret
        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$clientId, $configuredClientSecret);
        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Invalid signature verification.'], 401);
        }

        // 2. Fetch the user details and access
        $email = $request->input('email');
        if (blank($email)) {
            return response()->json(['message' => 'Email parameter is required.'], 400);
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $details = $request->input('user_details');
            if ($request->isMethod('post') && $details) {
                $user = User::create([
                    'name' => $details['name'] ?? 'SSO User',
                    'email' => $email,
                    'password' => bcrypt(Str::random(24)),
                    'sso_id' => $details['id'] ?? null,
                    'sso_uuid' => $details['uuid'] ?? null,
                    'sso_username' => $details['username'] ?? null,
                    'sso_account_type' => $details['user_type'] ?? null,
                    'user_type' => $details['user_type'] ?? 'employee',
                    'employee_no' => $details['employee_no'] ?? null,
                    'student_no' => $details['student_no'] ?? null,
                    'gender' => substr($details['gender'] ?? 'prefer_not', 0, 10),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
            } else {
                $allRoles = Role::with('permissions')->get();
                $allPermissions = Permission::pluck('name')->all();

                $rolePermissions = [];
                foreach ($allRoles as $role) {
                    $rolePermissions[$role->name] = $role->permissions->pluck('name')->all();
                }

                return response()->json([
                    'roles' => [],
                    'permissions' => [],
                    'all_roles' => $allRoles->pluck('name')->all(),
                    'all_permissions' => $allPermissions,
                    'role_permissions' => $rolePermissions,
                    'message' => 'User not found in client application database. Saving will auto-create this user.',
                ]);
            }
        }

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'roles' => 'array',
                'roles.*' => 'string',
                'permissions' => 'array',
                'permissions.*' => 'string',
            ]);

            $user->syncRoles($validated['roles'] ?? []);
            $user->syncPermissions($validated['permissions'] ?? []);

            return response()->json([
                'message' => 'User access tags updated successfully and user created/synced in client application.',
                'roles' => $user->roles->pluck('name')->all(),
                'permissions' => $user->getDirectPermissions()->pluck('name')->all(),
            ]);
        }

        // Retrieve roles and permissions
        $roles = $user->roles->pluck('name')->all();
        $permissions = $user->getDirectPermissions()->pluck('name')->all();
        $allRoles = Role::with('permissions')->get();
        $allPermissions = Permission::pluck('name')->all();

        $rolePermissions = [];
        foreach ($allRoles as $role) {
            $rolePermissions[$role->name] = $role->permissions->pluck('name')->all();
        }

        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions,
            'all_roles' => $allRoles->pluck('name')->all(),
            'all_permissions' => $allPermissions,
            'role_permissions' => $rolePermissions,
        ]);
    }
}
