<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            throw new \App\Exceptions\UnauthorizedException('Você não está autenticado.');
        }

        if (!auth()->user()->hasPermission($permission)) {
            throw new \App\Exceptions\ForbiddenException(
                "Você não tem permissão para acessar este recurso."
            );
        }

        return $next($request);
    }
}
