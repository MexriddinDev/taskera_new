@extends('layouts.app')
@section('title', 'Dashboard - Taskera')
@section('content')
<div>
    <h1 class="text-2xl font-bold mb-6">Dashboard</h1>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-700">
            <h3 class="text-gray-400 text-sm font-medium">Jami Ticketlar</h3>
            <p class="text-3xl font-bold text-white mt-2">{{ $stats['total_tickets'] ?? 0 }}</p>
        </div>
        <div class="bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-700">
            <h3 class="text-gray-400 text-sm font-medium">Ochiq Ticketlar</h3>
            <p class="text-3xl font-bold text-blue-400 mt-2">{{ $stats['open_tickets'] ?? 0 }}</p>
        </div>
        <div class="bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-700">
            <h3 class="text-gray-400 text-sm font-medium">Jarayonda</h3>
            <p class="text-3xl font-bold text-yellow-400 mt-2">{{ $stats['in_progress'] ?? 0 }}</p>
        </div>
        <div class="bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-700">
            <h3 class="text-gray-400 text-sm font-medium">Hal qilingan</h3>
            <p class="text-3xl font-bold text-green-400 mt-2">{{ $stats['resolved_tickets'] ?? 0 }}</p>
        </div>
    </div>

    <!-- Recent Tickets -->
    <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700">
            <h2 class="text-lg font-medium text-white">So'nggi Ticketlar</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Mavzu</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Holat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Prioritet</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">So'rovchi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Sana</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @forelse($recentTickets ?? [] as $ticket)
                    <tr class="hover:bg-gray-700/50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">#{{ $ticket->ticket_no }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-white font-medium"><a href="/tickets/{{ $ticket->id }}">{{ $ticket->subject }}</a></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 text-xs rounded-full bg-blue-500/20 text-blue-400">{{ $ticket->status_name }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $ticket->priority_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $ticket->requester_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">{{ \Carbon\Carbon::parse($ticket->created_at)->format('d.m.Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">Hech qanday ma'lumot topilmadi.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
