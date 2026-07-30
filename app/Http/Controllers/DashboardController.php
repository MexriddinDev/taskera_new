<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $stats = [
            'total_tickets' => DB::table('tickets')->whereNull('deleted_at')->count(),
            'open_tickets' => DB::table('tickets')->whereNull('deleted_at')->where('status_id', 1)->count(),
            'in_progress' => DB::table('tickets')->whereNull('deleted_at')->whereIn('status_id', [2, 3, 4])->count(),
            'resolved_tickets' => DB::table('tickets')->whereNull('deleted_at')->whereIn('status_id', [7, 8])->count(),
        ];

        $recentTickets = DB::table('tickets')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->leftJoin('users', 'tickets.requester_user_id', '=', 'users.id')
            ->leftJoin('employees', 'users.employee_id', '=', 'employees.id')
            ->whereNull('tickets.deleted_at')
            ->select(
                'tickets.id',
                'tickets.ticket_no',
                'tickets.subject',
                'tickets.created_at',
                'ticket_statuses.name as status_name',
                'ticket_priorities.name as priority_name',
                DB::raw("CONCAT(employees.first_name, ' ', employees.last_name) as requester_name")
            )
            ->orderBy('tickets.created_at', 'desc')
            ->limit(10)
            ->get();

        return view('dashboard', compact('stats', 'recentTickets'));
    }
}
