@extends('layouts.app')
@section('title', 'Analitika - Taskera')
@section('content')
<div>
    <h1 class="text-2xl font-bold text-white mb-6">Analitika</h1>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-gray-800 p-5 rounded-xl shadow border border-gray-700 text-center">
            <h3 class="text-gray-400 text-xs font-medium uppercase">Jami</h3>
            <p class="text-2xl font-bold text-white mt-1">{{ $ticketStats->total ?? 0 }}</p>
        </div>
        <div class="bg-gray-800 p-5 rounded-xl shadow border border-gray-700 text-center">
            <h3 class="text-gray-400 text-xs font-medium uppercase">Ochiq</h3>
            <p class="text-2xl font-bold text-blue-400 mt-1">{{ $ticketStats->open ?? 0 }}</p>
        </div>
        <div class="bg-gray-800 p-5 rounded-xl shadow border border-gray-700 text-center">
            <h3 class="text-gray-400 text-xs font-medium uppercase">Jarayonda</h3>
            <p class="text-2xl font-bold text-yellow-400 mt-1">{{ $ticketStats->in_progress ?? 0 }}</p>
        </div>
        <div class="bg-gray-800 p-5 rounded-xl shadow border border-gray-700 text-center">
            <h3 class="text-gray-400 text-xs font-medium uppercase">Hal qilingan</h3>
            <p class="text-2xl font-bold text-green-400 mt-1">{{ $ticketStats->resolved ?? 0 }}</p>
        </div>
        <div class="bg-gray-800 p-5 rounded-xl shadow border border-gray-700 text-center">
            <h3 class="text-gray-400 text-xs font-medium uppercase">Yopilgan</h3>
            <p class="text-2xl font-bold text-gray-400 mt-1">{{ $ticketStats->closed ?? 0 }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Monthly Trend -->
        <div class="bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-700">
            <h3 class="text-lg font-medium text-white mb-6">Oylik statistika</h3>
            <div class="h-64 flex items-end justify-between space-x-2 relative">
                <!-- Max value reference line -->
                <div class="absolute w-full border-t border-dashed border-gray-600 top-0 left-0"></div>
                <div class="absolute w-full border-t border-dashed border-gray-600 top-1/2 left-0"></div>
                
                @php
                    $maxVal = 100; // placeholder max
                    if(isset($monthlyTrend) && count($monthlyTrend) > 0) {
                        $maxVal = max(array_column(json_decode(json_encode($monthlyTrend), true), 'total'));
                    }
                    $maxVal = $maxVal > 0 ? $maxVal : 1;
                @endphp
                
                @foreach($monthlyTrend ?? [] as $trend)
                @php $height = ($trend->total / $maxVal) * 100; @endphp
                <div class="flex flex-col items-center flex-1 z-10 group">
                    <div class="w-full bg-indigo-500 rounded-t-sm hover:bg-indigo-400 transition relative" style="height: {{ $height }}%;">
                        <div class="opacity-0 group-hover:opacity-100 absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white text-xs py-1 px-2 rounded transition-opacity">{{ $trend->total }}</div>
                    </div>
                    <span class="text-xs text-gray-400 mt-2 rotate-45 origin-left">{{ $trend->month ?? 'N/A' }}</span>
                </div>
                @endforeach
                
                @if(empty($monthlyTrend) || count($monthlyTrend) == 0)
                <div class="absolute inset-0 flex items-center justify-center text-gray-500">Ma'lumot yo'q</div>
                @endif
            </div>
        </div>

        <!-- Top Categories -->
        <div class="bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-700">
            <h3 class="text-lg font-medium text-white mb-4">Eng ko'p ishlatilgan kategoriyalar</h3>
            <div class="space-y-4 mt-6">
                @forelse($topCategories ?? [] as $category)
                <div class="flex items-center justify-between">
                    <div class="flex items-center w-full">
                        <span class="text-sm text-gray-300 w-1/3 truncate">{{ $category->name }}</span>
                        <div class="w-2/3 ml-4">
                            @php $percent = ($category->total / ($ticketStats->total > 0 ? $ticketStats->total : 1)) * 100; @endphp
                            <div class="w-full bg-gray-700 rounded-full h-2.5">
                                <div class="bg-indigo-500 h-2.5 rounded-full" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-white ml-4">{{ $category->total }}</span>
                </div>
                @empty
                <p class="text-gray-500 text-sm text-center">Ma'lumot yo'q</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
