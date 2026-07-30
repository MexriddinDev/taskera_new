<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardApiController extends Controller
{
    public function stats()
    {
        $open = DB::table('tickets')->whereNull('deleted_at')->whereNotIn('status_id', [7, 8, 9, 10])->count();
        $breach = DB::table('tickets')->whereNull('deleted_at')->whereNotIn('status_id', [7, 8, 9, 10])->where('due_at', '<', now())->count();
        $critical = DB::table('tickets')->whereNull('deleted_at')->whereNotIn('status_id', [7, 8, 9, 10])->where('priority_id', 1)->count();
        $todayResolved = DB::table('tickets')->whereNull('deleted_at')->whereIn('status_id', [7, 8])->whereDate('resolved_at', now()->toDateString())->count();
        $engineers = DB::table('users')->whereNull('deleted_at')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'open_tickets' => $open,
                'sla_breach_tickets' => $breach,
                'critical_tickets' => $critical,
                'today_resolved' => $todayResolved,
                'active_engineers' => $engineers,
                'updated_at' => now()->toIso8601String()
            ]
        ]);
    }

    public function tickets(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $search = $request->query('search', '');

        $query = DB::table('tickets')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->leftJoin('ticket_sources', 'tickets.source_id', '=', 'ticket_sources.id')
            ->leftJoin('users as req_user', 'tickets.requester_user_id', '=', 'req_user.id')
            ->leftJoin('employees as req_emp', 'tickets.requester_employee_id', '=', 'req_emp.id')
            ->leftJoin('users as assign_user', 'tickets.assigned_user_id', '=', 'assign_user.id')
            ->leftJoin('departments', 'tickets.department_id', '=', 'departments.id')
            ->leftJoin('categories', 'tickets.category_id', '=', 'categories.id')
            ->whereNull('tickets.deleted_at');

        if ($filter === 'critical') {
            $query->where('tickets.priority_id', 1);
        } elseif ($filter === 'breach') {
            $query->whereNotIn('tickets.status_id', [7, 8, 9, 10])->where('tickets.due_at', '<', now());
        } elseif ($filter === 'open') {
            $query->whereNotIn('tickets.status_id', [7, 8, 9, 10]);
        } elseif ($filter === 'assigned') {
            $query->whereNotNull('tickets.assigned_user_id');
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('tickets.ticket_no', 'like', "%{$search}%")
                  ->orWhere('tickets.subject', 'like', "%{$search}%")
                  ->orWhere('tickets.description', 'like', "%{$search}%")
                  ->orWhere('req_emp.first_name', 'like', "%{$search}%")
                  ->orWhere('req_emp.last_name', 'like', "%{$search}%");
            });
        }

        $tickets = $query->select(
                'tickets.*',
                'ticket_statuses.name as status_name',
                'ticket_statuses.code as status_code',
                'ticket_statuses.color as status_color',
                'ticket_priorities.name as priority_name',
                'ticket_priorities.code as priority_code',
                'ticket_priorities.color as priority_color',
                'ticket_sources.name as source_name',
                'req_user.username as requester_username',
                'req_emp.first_name as requester_first_name',
                'req_emp.last_name as requester_last_name',
                'assign_user.username as assignee_username',
                'departments.name as department_name',
                'categories.name as category_name'
            )
            ->orderBy('tickets.created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $tickets->count(),
            'data' => $tickets
        ]);
    }

    public function quickTicket(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'description' => 'required|string',
            'priority_id' => 'required|integer',
            'department_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
        ]);

        $ticketCount = DB::table('tickets')->count() + 101;
        $ticketNo = 'INC-' . str_pad($ticketCount, 6, '0', STR_PAD_LEFT);

        $dueHours = 4;
        if ($validated['priority_id'] == 1) $dueHours = 1; // Critical
        if ($validated['priority_id'] == 2) $dueHours = 2; // High

        $ticketId = DB::table('tickets')->insertGetId([
            'public_id' => Str::uuid(),
            'organization_id' => 1,
            'ticket_no' => $ticketNo,
            'ticket_type' => 'INCIDENT',
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'status_id' => 1, // NEW
            'priority_id' => $validated['priority_id'],
            'source_id' => 1, // WEB
            'requester_user_id' => auth()->id() ?? 1,
            'department_id' => $validated['department_id'] ?? 1,
            'category_id' => $validated['category_id'] ?? 1,
            'due_at' => now()->addHours($dueHours),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Audit Log
        DB::table('audit_logs')->insert([
            'organization_id' => 1,
            'actor_user_id' => auth()->id() ?? 1,
            'action' => 'TICKET_CREATED',
            'auditable_type' => 'Ticket',
            'auditable_id' => $ticketId,
            'correlation_id' => Str::uuid(),
            'source' => 'WEB_API',
            'reason' => "Yangi zayavka {$ticketNo} yaratildi: {$validated['subject']}",
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Zayavka #{$ticketNo} muvaffaqiyatli qabul qilindi!",
            'ticket_id' => $ticketId,
            'ticket_no' => $ticketNo
        ]);
    }

    public function search(Request $request)
    {
        $q = $request->query('q', '');
        if (strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $tickets = DB::table('tickets')
            ->whereNull('deleted_at')
            ->where(function($query) use ($q) {
                $query->where('ticket_no', 'like', "%{$q}%")
                      ->orWhere('subject', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'ticket_no as title', 'subject as subtitle', DB::raw("'ticket' as type")]);

        $employees = DB::table('employees')
            ->whereNull('deleted_at')
            ->where(function($query) use ($q) {
                $query->where('first_name', 'like', "%{$q}%")
                      ->orWhere('last_name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', DB::raw("CONCAT(first_name, ' ', last_name) as title"), 'email as subtitle', DB::raw("'employee' as type")]);

        $departments = DB::table('departments')
            ->whereNull('deleted_at')
            ->where('name', 'like', "%{$q}%")
            ->limit(5)
            ->get(['id', 'name as title', 'code as subtitle', DB::raw("'department' as type")]);

        $results = array_merge($tickets->toArray(), $employees->toArray(), $departments->toArray());

        return response()->json([
            'success' => true,
            'query' => $q,
            'data' => $results
        ]);
    }
}
