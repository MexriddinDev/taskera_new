<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        $ticketStats = DB::table('tickets')
            ->select(
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as open"),
                DB::raw("SUM(CASE WHEN status_id IN (2,3,4) THEN 1 ELSE 0 END) as in_progress"),
                DB::raw("SUM(CASE WHEN status_id IN (7,8) THEN 1 ELSE 0 END) as resolved"),
                DB::raw("SUM(CASE WHEN status_id = 6 THEN 1 ELSE 0 END) as closed")
            )
            ->whereNull('deleted_at')
            ->first();

        $monthlyTrend = DB::table('tickets')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw("COUNT(*) as total")
            )
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy('month')
            ->get();

        $topCategories = DB::table('tickets')
            ->leftJoin('categories', 'tickets.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw("COUNT(*) as total"))
            ->whereNull('tickets.deleted_at')
            ->groupBy('categories.name')
            ->orderByRaw('total DESC')
            ->limit(10)
            ->get();

        return view('analytics', compact('ticketStats', 'monthlyTrend', 'topCategories'));
    }
}
