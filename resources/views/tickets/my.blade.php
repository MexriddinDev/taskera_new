@extends('layouts.app')
@section('title', 'Mening Ticketlarim - Taskera')
@section('content')
<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-white">Mening Ticketlarim</h1>
    </div>

    <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Mavzu</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Kategoriya</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Prioritet</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Holat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">So'rovchi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Sana</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Amallar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @forelse($myTickets ?? [] as $ticket)
                    <tr class="hover:bg-gray-700/50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">#{{ $ticket->ticket_no ?? $ticket->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-white font-medium">{{ $ticket->subject }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $ticket->category_name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            {{ $ticket->priority_name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 text-xs rounded-full bg-indigo-500/20 text-indigo-400">{{ $ticket->status_name ?? '-' }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ trim(($ticket->requester_first_name ?? '') . ' ' . ($ticket->requester_last_name ?? '')) ?: '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">{{ \Carbon\Carbon::parse($ticket->created_at)->format('d.m.Y H:i') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="/tickets/{{ $ticket->id }}" class="text-indigo-400 hover:text-indigo-300">Batafsil</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">Sizga biriktirilgan ticketlar yo'q.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
