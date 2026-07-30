<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\DepartmentController;
use App\Modules\Ticketing\Presentation\Http\Controllers\TicketController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeePortalController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\ProfileController;

// Main Entrance Route
Route::get('/', function () {
    if (!auth()->check()) {
        return redirect('/login');
    }
    $user = auth()->user();
    if ($user->isSuperAdmin() || $user->isDepartmentAdmin()) {
        return redirect('/dashboard');
    }
    return redirect('/portal');
});

// Authentication Routes
Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store']);
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Employee Service Portal Routes (Accessible to all logged-in users)
Route::middleware(['auth'])->group(function () {
    Route::get('/portal', [EmployeePortalController::class, 'index'])->name('portal.index');
    Route::get('/portal/request/new', [EmployeePortalController::class, 'createTicket'])->name('portal.create');
    Route::post('/portal/request', [EmployeePortalController::class, 'storeTicket'])->name('portal.store');
    Route::get('/portal/request/{id}', [EmployeePortalController::class, 'showTicket'])->name('portal.show');
    Route::post('/portal/request/{id}/comment', [EmployeePortalController::class, 'addComment'])->name('portal.comment');
    Route::post('/portal/request/{id}/reject', [EmployeePortalController::class, 'rejectSolution'])->name('portal.reject');
    Route::post('/portal/request/{id}/rate', [EmployeePortalController::class, 'rateTicket'])->name('portal.rate');
});

// Kanban, Open Tasks, My Tasks & Profile Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/tickets/kanban', [KanbanController::class, 'index']);
    Route::post('/api/v1/tickets/{id}/update-status', [KanbanController::class, 'updateStatus']);

    Route::get('/tickets/open', function () {
        $openTickets = DB::table('tickets')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->leftJoin('categories', 'tickets.category_id', '=', 'categories.id')
            ->leftJoin('users as req_user', 'tickets.requester_user_id', '=', 'req_user.id')
            ->leftJoin('employees as req_emp', 'req_user.employee_id', '=', 'req_emp.id')
            ->whereNull('tickets.deleted_at')
            ->whereIn('tickets.status_id', [1])
            ->select(
                'tickets.*',
                'ticket_statuses.name as status_name',
                'ticket_statuses.code as status_code',
                'ticket_priorities.name as priority_name',
                'categories.name as category_name',
                'req_emp.first_name as requester_first_name',
                'req_emp.last_name as requester_last_name'
            )
            ->orderBy('tickets.created_at', 'desc')
            ->get();
        return view('tickets.open', compact('openTickets'));
    });

    Route::get('/tickets/my', function () {
        $userId = auth()->id();
        $myTickets = DB::table('tickets')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->leftJoin('categories', 'tickets.category_id', '=', 'categories.id')
            ->leftJoin('users as req_user', 'tickets.requester_user_id', '=', 'req_user.id')
            ->leftJoin('employees as req_emp', 'req_user.employee_id', '=', 'req_emp.id')
            ->whereNull('tickets.deleted_at')
            ->where('tickets.assigned_user_id', $userId)
            ->select(
                'tickets.*',
                'ticket_statuses.name as status_name',
                'ticket_statuses.code as status_code',
                'ticket_priorities.name as priority_name',
                'categories.name as category_name',
                'req_emp.first_name as requester_first_name',
                'req_emp.last_name as requester_last_name'
            )
            ->orderBy('tickets.created_at', 'desc')
            ->get();
        return view('tickets.my', compact('myTickets'));
    });

    Route::get('/profile', [ProfileController::class, 'index']);
});

// Executive Dashboard & Admin Management Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isDepartmentAdmin()) {
            return redirect('/portal')->with('error', 'Sizda Boshqaruv Markaziga kirish ruxsati yo\'q. Faoliyat xizmat portali orqali amalga oshiriladi.');
        }
        return app(DashboardController::class)->index();
    });

    // Monitoring & Analytics Portal
    Route::get('/analytics', function () {
        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isDepartmentAdmin()) {
            return redirect('/portal')->with('error', 'Sizda Analitika va Monitoring bo\'limiga kirish ruxsati yo\'q.');
        }
        return app(AnalyticsController::class)->index();
    });

    // Dynamic Roles & RBAC Management
    Route::get('/settings/roles', function () {
        if (!auth()->user()->isSuperAdmin()) {
            return redirect('/portal')->with('error', 'Faqat Super Admin rolgini sozlay oladi.');
        }
        return app(RoleController::class)->index(request());
    });
    Route::post('/settings/roles', [RoleController::class, 'store']);
    Route::delete('/settings/roles/{id}', [RoleController::class, 'destroy']);

    // Dynamic Departments Management
    Route::get('/organization/departments', [DepartmentController::class, 'index']);
    Route::post('/organization/departments', [DepartmentController::class, 'store']);
    Route::delete('/organization/departments/{id}', [DepartmentController::class, 'destroy']);

    // 1. Open / All Tickets Register Page
    Route::get('/tickets', function () {
        $tickets = auth()->user()->getAccessibleTicketsQuery()->whereNotIn('status_id', [7, 8])->get();
        $isResolvedPage = false;
        return view('tickets.index', compact('tickets', 'isResolvedPage'));
    });

    // 2. Dedicated Resolved Tickets Register Page
    Route::get('/tickets/resolved', function () {
        $tickets = auth()->user()->getAccessibleTicketsQuery()->whereIn('status_id', [7, 8])->get();
        $isResolvedPage = true;
        return view('tickets.index', compact('tickets', 'isResolvedPage'));
    });

    Route::get('/tickets/create', function () {
        return view('tickets.create');
    });

    Route::get('/tickets/{id}', function ($id) {
        $ticket = \Illuminate\Support\Facades\DB::table('tickets')->where('id', $id)->first();
        if (!$ticket) abort(404);
        $comments = \Illuminate\Support\Facades\DB::table('comments')
            ->where('commentable_type', 'App\\Modules\\Ticketing\\Infrastructure\\Eloquent\\Ticket')
            ->where('commentable_id', $id)
            ->get();
        return view('tickets.show', compact('ticket', 'comments'));
    });

    Route::post('/tickets', [TicketController::class, 'store']);
    Route::post('/tickets/{id}/transition', [TicketController::class, 'transition']);
    Route::post('/tickets/{id}/assign', [TicketController::class, 'assign']);

    Route::post('/tickets/{id}/comments', function (\Illuminate\Http\Request $request, $id, \App\Modules\Ticketing\Domain\Services\AddCommentService $service) {
        $validated = $request->validate(['body' => 'required|string']);
        $service->execute([
            'organization_id' => 1,
            'commentable_type' => 'App\\Modules\\Ticketing\\Infrastructure\\Eloquent\\Ticket',
            'commentable_id' => $id,
            'author_user_id' => auth()->id(),
            'body' => $validated['body'],
        ]);
        return redirect()->back()->with('success', 'Izoh muvaffaqiyatli qo\'shildi!');
    });
});

// TaskFlow SPA (web_sites) — client-side routing catch-all
Route::get('/web_sites/{any?}', function () {
    return file_get_contents(public_path('web_sites/index.html'));
})->where('any', '.*');


