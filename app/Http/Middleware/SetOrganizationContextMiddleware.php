<?php

namespace App\Http\Middleware;

use App\Support\CurrentOrg;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetOrganizationContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Xavfsizlik: organizatsiya faqat autentifikatsiyalangan foydalanuvchidan
        // olinadi — client headeriga ishonilmaydi (CurrentOrg::id()).
        $orgId = CurrentOrg::id($request);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET LOCAL app.current_organization_id = ?', [$orgId]);
        }

        return $next($request);
    }
}
