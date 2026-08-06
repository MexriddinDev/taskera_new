@extends('layouts.app')
@section('title', 'Ticket - Taskera')
@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <div class="flex items-center space-x-3 mb-1">
                        <span class="text-gray-400 font-mono">#{{ $ticket->ticket_no ?? $ticket->id }}</span>
                        <h2 class="text-2xl font-bold text-white">{{ $ticket->subject }}</h2>
                    </div>
                    <p class="text-sm text-gray-400">Yaratildi: {{ \Carbon\Carbon::parse($ticket->created_at)->format('d.m.Y H:i') }}</p>
                </div>
            </div>
            
            <div class="prose prose-invert max-w-none mt-6 bg-gray-900/50 p-4 rounded-lg border border-gray-700">
                <p class="whitespace-pre-line text-gray-300">{{ $ticket->description }}</p>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-6">
            <h3 class="text-lg font-medium text-white mb-4">Ish jarayoni va Izohlar</h3>
            
            <div class="space-y-4 mb-6">
                @forelse($comments ?? [] as $comment)
                <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-700">
                    <p class="text-sm text-gray-400 mb-2">{{ \Carbon\Carbon::parse($comment->created_at)->format('d.m.Y H:i') }}</p>
                    <p class="text-gray-200">{{ $comment->body }}</p>
                </div>
                @empty
                <p class="text-gray-500 text-sm">Hali ma'lumot yo'q.</p>
                @endforelse
            </div>

            <form action="/tickets/{{ $ticket->id }}/comments" method="POST">
                @csrf
                <textarea name="body" rows="3" required placeholder="Izoh yoki bajarilgan ish haqida yozing..." class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white mb-3"></textarea>
                <div class="flex justify-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Yuborish</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sidebar Properties -->
    <div class="space-y-6">
        <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-6">
            <h3 class="text-lg font-medium text-white mb-4">Xususiyatlar</h3>
            <ul class="space-y-4 text-sm">
                <li class="flex flex-col border-b border-gray-700 pb-3">
                    <span class="text-gray-400 mb-1">Holat:</span>
                    <span class="text-white font-medium bg-gray-700 px-3 py-1 rounded-md inline-block w-max">{{ $ticket->status_id ?? 'Noma\'lum' }}</span>
                </li>
                <li class="flex flex-col border-b border-gray-700 pb-3">
                    <span class="text-gray-400 mb-1">Prioritet:</span>
                    <span class="text-white font-medium bg-gray-700 px-3 py-1 rounded-md inline-block w-max">{{ $ticket->priority_id ?? 'Noma\'lum' }}</span>
                </li>
            </ul>
            
            <div class="mt-6 pt-4 border-t border-gray-700">
                <p class="text-xs text-gray-500 text-center">Tez orada tahrirlash funksiyalari qo'shiladi</p>
            </div>
        </div>
    </div>
</div>
@endsection
