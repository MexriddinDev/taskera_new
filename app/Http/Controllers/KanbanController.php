<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KanbanController extends Controller
{
    public function index(): View
    {
        $statuses = DB::table('ticket_statuses')->orderBy('id')->get();

        $tickets = DB::table('tickets')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->leftJoin('categories', 'tickets.category_id', '=', 'categories.id')
            ->leftJoin('users as req_user', 'tickets.requester_user_id', '=', 'req_user.id')
            ->leftJoin('employees as req_emp', 'req_user.employee_id', '=', 'req_emp.id')
            ->leftJoin('users as assigned_user', 'tickets.assigned_user_id', '=', 'assigned_user.id')
            ->leftJoin('employees as assigned_emp', 'assigned_user.employee_id', '=', 'assigned_emp.id')
            ->whereNull('tickets.deleted_at')
            ->select(
                'tickets.id',
                'tickets.ticket_no',
                'tickets.subject',
                'tickets.status_id',
                'tickets.assigned_user_id',
                'tickets.created_at',
                'ticket_statuses.name as status_name',
                'ticket_statuses.code as status_code',
                'ticket_priorities.name as priority_name',
                'categories.name as category_name',
                DB::raw("CONCAT(req_emp.first_name, ' ', req_emp.last_name) as requester_name"),
                DB::raw("CONCAT(assigned_emp.first_name, ' ', assigned_emp.last_name) as assigned_name")
            )
            ->orderBy('tickets.created_at', 'desc')
            ->get();

        $grouped = [];
        foreach ($tickets as $ticket) {
            $grouped[$ticket->status_id][] = $ticket;
        }

        return view('tickets.kanban', compact('statuses', 'grouped'));
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'status_id' => 'required|integer|exists:ticket_statuses,id',
        ]);

        DB::table('tickets')->where('id', $id)->update([
            'status_id' => $validated['status_id'],
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Status updated']);
    }
}
