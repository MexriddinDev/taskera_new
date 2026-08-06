@extends('layouts.app')
@section('title', 'So\'rovni ko\'rish - Taskera')
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Ticket Details -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-1">{{ $ticket->subject }}</h2>
                    <p class="text-sm text-gray-400">Yaratildi: {{ \Carbon\Carbon::parse($ticket->created_at)->format('d.m.Y H:i') }}</p>
                </div>
                <div>
                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">{{ $ticket->status_name }}</span>
                </div>
            </div>
            
            <div class="prose prose-invert max-w-none mt-6">
                <p class="whitespace-pre-line text-gray-300">{{ $ticket->description }}</p>
            </div>
        </div>

        @if(isset($ticket->status_code) && $ticket->status_code == 'RESOLVED')
            <div class="bg-gray-800 rounded-xl shadow-lg border border-green-700/50 p-6">
                <h3 class="text-lg font-medium text-white mb-4">So'rov hal qilindi</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Rate Form -->
                    <div class="bg-gray-700/50 p-4 rounded-lg">
                        <h4 class="font-medium text-white mb-2">Baholash</h4>
                        <form action="/portal/request/{{ $ticket->id }}/rate" method="POST">
                            @csrf
                            <select name="rating" required class="block w-full bg-gray-900 border border-gray-600 rounded-lg px-3 py-2 mb-3 text-white">
                                <option value="">Baho bering...</option>
                                <option value="5">5 - Juda yaxshi</option>
                                <option value="4">4 - Yaxshi</option>
                                <option value="3">3 - Qoniqarli</option>
                                <option value="2">2 - Yomon</option>
                                <option value="1">1 - Juda yomon</option>
                            </select>
                            <textarea name="feedback" rows="2" placeholder="Fikringiz (ixtiyoriy)" class="block w-full bg-gray-900 border border-gray-600 rounded-lg px-3 py-2 mb-3 text-white"></textarea>
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg text-sm font-medium">Tasdiqlash</button>
                        </form>
                    </div>

                    <!-- Reject Form -->
                    <div class="bg-gray-700/50 p-4 rounded-lg">
                        <h4 class="font-medium text-white mb-2">Rad etish</h4>
                        <form action="/portal/request/{{ $ticket->id }}/reject" method="POST">
                            @csrf
                            <textarea name="reason" rows="3" required placeholder="Nima uchun rad etyapsiz?" class="block w-full bg-gray-900 border border-gray-600 rounded-lg px-3 py-2 mb-3 text-white"></textarea>
                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg text-sm font-medium">Rad etish</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <!-- Comments -->
        <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-6">
            <h3 class="text-lg font-medium text-white mb-4">Izohlar</h3>
            
            <div class="space-y-4 mb-6">
                @forelse($comments ?? [] as $comment)
                <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-700">
                    <p class="text-sm text-gray-400 mb-2">{{ \Carbon\Carbon::parse($comment->created_at)->format('d.m.Y H:i') }}</p>
                    <p class="text-gray-200">{{ $comment->body }}</p>
                </div>
                @empty
                <p class="text-gray-500 text-sm">Hali izohlar yo'q.</p>
                @endforelse
            </div>

            <!-- Comment Form -->
            <form action="/portal/request/{{ $ticket->id }}/comment" method="POST">
                @csrf
                <textarea name="body" rows="3" required placeholder="Izoh yozing..." class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white mb-3"></textarea>
                <div class="flex justify-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Yuborish</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-6">
            <h3 class="text-lg font-medium text-white mb-4">Ma'lumotlar</h3>
            <ul class="space-y-3 text-sm">
                <li class="flex justify-between border-b border-gray-700 pb-2">
                    <span class="text-gray-400">Kategoriya:</span>
                    <span class="text-white font-medium">{{ $ticket->category_name }}</span>
                </li>
                <li class="flex justify-between border-b border-gray-700 pb-2">
                    <span class="text-gray-400">Prioritet:</span>
                    <span class="text-white font-medium">{{ $ticket->priority_name }}</span>
                </li>
                <li class="flex justify-between border-b border-gray-700 pb-2">
                    <span class="text-gray-400">So'rovchi:</span>
                    <span class="text-white font-medium">{{ $ticket->requester_name }}</span>
                </li>
                <li class="flex justify-between">
                    <span class="text-gray-400">Mas'ul xodim:</span>
                    <span class="text-white font-medium">{{ $ticket->assigned_name ?? 'Biriktirilmagan' }}</span>
                </li>
            </ul>
        </div>
    </div>
</div>
@endsection
