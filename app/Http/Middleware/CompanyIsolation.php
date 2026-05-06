<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CompanyIsolation
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $companyId = $request->user()->company_id;
            $request->attributes->set('company_id', $companyId);
        }

        return $next($request);
    }
}
