<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployeePortalController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $tickets = DB::table('tickets')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->leftJoin('categories', 'tickets.category_id', '=', 'categories.id')
            ->whereNull('tickets.deleted_at')
            ->where('tickets.requester_user_id', $user->id)
            ->select(
                'tickets.*',
                'ticket_statuses.name as status_name',
                'ticket_statuses.code as status_code',
                'ticket_priorities.name as priority_name',
                'categories.name as category_name'
            )
            ->orderBy('tickets.created_at', 'desc')
            ->get();

        return view('portal.index', compact('tickets'));
    }

    public function createTicket(): View
    {
        $categories = DB::table('categories')->whereNull('deleted_at')->get();
        $priorities = DB::table('ticket_priorities')->get();

        return view('portal.create', compact('categories', 'priorities'));
    }

    public function storeTicket(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'description' => 'required|string',
            'category_id' => 'nullable|integer|exists:categories,id',
            'priority_id' => 'required|integer|exists:ticket_priorities,id',
        ]);

        $user = auth()->user();
        $employee = DB::table('employees')->where('id', $user->employee_id)->first();

        $ticketNo = 'TKT-' . strtoupper(uniqid());

        DB::table('tickets')->insert([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'ticket_no' => $ticketNo,
            'ticket_type' => 'INCIDENT',
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'status_id' => 1,
            'priority_id' => $validated['priority_id'],
            'source_id' => 1,
            'requester_user_id' => $user->id,
            'requester_employee_id' => $employee?->id,
            'department_id' => $employee?->department_id,
            'branch_id' => $employee?->branch_id,
            'category_id' => $validated['category_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('portal.index')->with('success', 'Ticket created successfully');
    }

    public function showTicket($id): View
    {
        $ticket = DB::table('tickets')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->leftJoin('categories', 'tickets.category_id', '=', 'categories.id')
            ->leftJoin('users as req_user', 'tickets.requester_user_id', '=', 'req_user.id')
            ->leftJoin('employees as req_emp', 'req_user.employee_id', '=', 'req_emp.id')
            ->leftJoin('users as assigned_user', 'tickets.assigned_user_id', '=', 'assigned_user.id')
            ->leftJoin('employees as assigned_emp', 'assigned_user.employee_id', '=', 'assigned_emp.id')
            ->where('tickets.id', $id)
            ->where('tickets.requester_user_id', auth()->id())
            ->select(
                'tickets.*',
                'ticket_statuses.name as status_name',
                'ticket_statuses.code as status_code',
                'ticket_priorities.name as priority_name',
                'categories.name as category_name',
                DB::raw("CONCAT(req_emp.first_name, ' ', req_emp.last_name) as requester_name"),
                DB::raw("CONCAT(assigned_emp.first_name, ' ', assigned_emp.last_name) as assigned_name")
            )
            ->first();

        if (!$ticket) {
            abort(404);
        }

        $comments = DB::table('comments')
            ->where('commentable_type', 'App\\Modules\\Ticketing\\Infrastructure\\Eloquent\\Ticket')
            ->where('commentable_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        return view('portal.show', compact('ticket', 'comments'));
    }

    public function addComment(Request $request, $id): RedirectResponse
    {
        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $ticket = DB::table('tickets')
            ->where('id', $id)
            ->where('requester_user_id', auth()->id())
            ->first();

        if (!$ticket) {
            abort(404);
        }

        DB::table('comments')->insert([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'commentable_type' => 'App\\Modules\\Ticketing\\Infrastructure\\Eloquent\\Ticket',
            'commentable_id' => $id,
            'author_user_id' => auth()->id(),
            'type_id' => 1,
            'source_id' => 1,
            'body' => $validated['body'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Comment added successfully');
    }

    public function rejectSolution(Request $request, $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $ticket = DB::table('tickets')
            ->where('id', $id)
            ->where('requester_user_id', auth()->id())
            ->first();

        if (!$ticket) {
            abort(404);
        }

        DB::table('comments')->insert([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'commentable_type' => 'App\\Modules\\Ticketing\\Infrastructure\\Eloquent\\Ticket',
            'commentable_id' => $id,
            'author_user_id' => auth()->id(),
            'type_id' => 1,
            'source_id' => 1,
            'body' => 'Solution rejected: ' . $validated['reason'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tickets')->where('id', $id)->update([
            'status_id' => 2,
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Solution rejected');
    }

    public function rateTicket(Request $request, $id): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string',
        ]);

        $ticket = DB::table('tickets')
            ->where('id', $id)
            ->where('requester_user_id', auth()->id())
            ->first();

        if (!$ticket) {
            abort(404);
        }

        DB::table('tickets')->where('id', $id)->update([
            'status_id' => 7,
            'client_rating' => $validated['rating'],
            'resolved_at' => now(),
            'metadata' => DB::raw("jsonb_set(COALESCE(metadata, '{}'), '{rating}', '" . $validated['rating'] . "')"),
            'updated_at' => now(),
        ]);

        if (!empty($validated['feedback'])) {
            DB::table('comments')->insert([
                'public_id' => (string) \Illuminate\Support\Str::uuid(),
                'organization_id' => 1,
                'commentable_type' => 'App\\Modules\\Ticketing\\Infrastructure\\Eloquent\\Ticket',
                'commentable_id' => $id,
                'author_user_id' => auth()->id(),
                'type_id' => 1,
                'source_id' => 1,
                'body' => 'Rating: ' . $validated['rating'] . '/5. Feedback: ' . ($validated['feedback'] ?? ''),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'Thank you for your rating');
    }
}
