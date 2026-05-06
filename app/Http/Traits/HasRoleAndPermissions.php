<?php

namespace App\Http\Traits;

trait HasRoleAndPermissions
{
    /**
     * Check if current user has a role
     */
    protected function userHasRole(string|array $roles): bool
    {
        return auth()->check() && auth()->user()->hasRole($roles);
    }

    /**
     * Check if current user has a permission
     */
    protected function userHasPermission(string|array $permissions): bool
    {
        return auth()->check() && auth()->user()->hasPermission($permissions);
    }

    /**
     * Check if current user has any of the permissions
     */
    protected function userHasAnyPermission(array $permissions): bool
    {
        return auth()->check() && auth()->user()->hasAnyPermission($permissions);
    }

    /**
     * Check if current user has all permissions
     */
    protected function userHasAllPermissions(array $permissions): bool
    {
        return auth()->check() && auth()->user()->hasAllPermissions($permissions);
    }

    /**
     * Authorize or throw exception if user doesn't have permission
     */
    protected function authorizePermission(string $permission): void
    {
        if (!$this->userHasPermission($permission)) {
            throw new \App\Exceptions\ForbiddenException(
                "Você não tem permissão para acessar este recurso."
            );
        }
    }

    /**
     * Authorize or throw exception if user doesn't have role
     */
    protected function authorizeRole(string $role): void
    {
        if (!$this->userHasRole($role)) {
            throw new \App\Exceptions\ForbiddenException(
                "Você não tem permissão para acessar este recurso."
            );
        }
    }
}
