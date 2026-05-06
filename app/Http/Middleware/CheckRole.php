<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!auth()->check()) {
            throw new \App\Exceptions\UnauthorizedException('Você não está autenticado.');
        }

        if (!auth()->user()->hasRole($role)) {
            throw new \App\Exceptions\ForbiddenException(
                "Você não tem permissão para acessar este recurso."
            );
        }

        return $next($request);
    }
}
