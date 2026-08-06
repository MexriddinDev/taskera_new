@extends('layouts.app')
@section('title', 'Kanban Board - Taskera')
@section('content')
<div class="h-full flex flex-col">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-white">Kanban Doskasi</h1>
    </div>

    <!-- Kanban Board -->
    <div class="flex flex-1 overflow-x-auto pb-4 space-x-6 kanban-board">
        @foreach($statuses ?? [] as $status)
        <div class="flex-shrink-0 w-80 bg-gray-800 rounded-xl shadow-lg border border-gray-700 flex flex-col kanban-column" data-status-id="{{ $status->id }}">
            <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                <h3 class="font-medium text-white">{{ $status->name }}</h3>
                <span class="bg-gray-700 text-gray-300 text-xs py-1 px-2 rounded-full">{{ count($grouped[$status->id] ?? []) }}</span>
            </div>
            
            <div class="p-3 flex-1 overflow-y-auto space-y-3 kanban-list min-h-[200px]" ondrop="drop(event)" ondragover="allowDrop(event)">
                @foreach($grouped[$status->id] ?? [] as $ticket)
                <div class="bg-gray-700 rounded-lg p-4 shadow cursor-grab border border-gray-600 hover:border-indigo-500 transition ticket-card" draggable="true" ondragstart="drag(event)" id="ticket-{{ $ticket->id }}" data-id="{{ $ticket->id }}">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs font-mono text-gray-400">#{{ $ticket->ticket_no ?? $ticket->id }}</span>
                        @if($ticket->priority_id)
                        <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                        @endif
                    </div>
                    <a href="/tickets/{{ $ticket->id }}" class="text-sm text-white font-medium hover:text-indigo-400 block mb-2">{{ $ticket->subject }}</a>
                    <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-600">
                        <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($ticket->created_at)->format('d.m H:i') }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
    function allowDrop(ev) {
        ev.preventDefault();
        ev.currentTarget.classList.add('bg-gray-700/50');
    }

    function drag(ev) {
        ev.dataTransfer.setData("text", ev.target.id);
        ev.target.classList.add('opacity-50');
    }

    function drop(ev) {
        ev.preventDefault();
        
        const list = ev.currentTarget;
        list.classList.remove('bg-gray-700/50');
        
        var data = ev.dataTransfer.getData("text");
        var draggedElement = document.getElementById(data);
        
        if (draggedElement) {
            draggedElement.classList.remove('opacity-50');
            list.appendChild(draggedElement);
            
            var ticketId = draggedElement.getAttribute('data-id');
            var column = list.closest('.kanban-column');
            var newStatusId = column.getAttribute('data-status-id');
            
            updateTicketStatus(ticketId, newStatusId);
        }
    }
    
    // Remove background on drag leave
    document.querySelectorAll('.kanban-list').forEach(list => {
        list.addEventListener('dragleave', (e) => {
            e.currentTarget.classList.remove('bg-gray-700/50');
        });
        list.addEventListener('dragend', (e) => {
            document.querySelectorAll('.ticket-card').forEach(card => card.classList.remove('opacity-50'));
        });
    });

    function updateTicketStatus(ticketId, statusId) {
        fetch(`/api/v1/tickets/${ticketId}/update-status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ status_id: statusId })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Success:', data);
            // Optionally update counters
        })
        .catch((error) => {
            console.error('Error:', error);
            alert('Holatni o\'zgartirishda xatolik yuz berdi. Iltimos sahifani yangilang.');
        });
    }
</script>
@endpush
@endsection
