<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class AuthorizationHelper
{
    /**
     * Check if authenticated user has a specific role
     */
    public static function hasRole(string|array $roles): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return Auth::user()->hasRole($roles);
    }

    /**
     * Check if authenticated user has a specific permission
     */
    public static function hasPermission(string|array $permissions): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return Auth::user()->hasPermission($permissions);
    }

    /**
     * Check if authenticated user has any of the permissions
     */
    public static function hasAnyPermission(array $permissions): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return Auth::user()->hasAnyPermission($permissions);
    }

    /**
     * Check if authenticated user has all permissions
     */
    public static function hasAllPermissions(array $permissions): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return Auth::user()->hasAllPermissions($permissions);
    }

    /**
     * Get authenticated user's roles
     */
    public static function getRoles(): array
    {
        if (!Auth::check()) {
            return [];
        }

        return Auth::user()->roles()->pluck('name')->toArray();
    }

    /**
     * Get authenticated user's permissions
     */
    public static function getPermissions(): array
    {
        if (!Auth::check()) {
            return [];
        }

        return Auth::user()
            ->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn($role) => $role->permissions)
            ->pluck('name')
            ->unique()
            ->toArray();
    }
}
