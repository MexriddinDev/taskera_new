<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetOrganizationContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $orgId = $request->header('X-Organization-Id') ?? 1;

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SET LOCAL app.current_organization_id = '{$orgId}'");
        }

        return $next($request);
    }
}
