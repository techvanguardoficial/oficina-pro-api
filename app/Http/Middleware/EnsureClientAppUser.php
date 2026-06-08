<?php

namespace App\Http\Middleware;

use App\Models\ClientAppUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientAppUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() instanceof ClientAppUser) {
            return response()->json([
                'error'   => true,
                'message' => 'Acesso não autorizado.',
                'code'    => 'UNAUTHORIZED',
            ], 401);
        }

        return $next($request);
    }
}
