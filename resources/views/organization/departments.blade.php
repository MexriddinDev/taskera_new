@extends('layouts.app')
@section('title', 'Bo\'limlar - Taskera')
@section('content')
<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-white">Tashkilot Bo'limlari</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Departments List -->
        <div class="lg:col-span-2">
            <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Kod</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Nomi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Filial</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($departments ?? [] as $department)
                            <tr class="hover:bg-gray-700/50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300 font-mono">{{ $department->code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white font-medium">{{ $department->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">{{ $department->branch->name ?? 'Asosiy' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <form action="/organization/departments/{{ $department->id }}" method="POST" class="inline" onsubmit="return confirm('Rostdan ham o\'chirmoqchimisiz?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-300">O'chirish</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">Bo'limlar mavjud emas.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Department Form -->
        <div class="space-y-6">
            <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-6">
                <h3 class="text-lg font-medium text-white mb-4">Yangi Bo'lim Qo'shish</h3>
                
                <form action="/organization/departments" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-300">Kod</label>
                        <input type="text" name="code" id="code" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white font-mono placeholder-gray-500" placeholder="IT">
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300">Nomi</label>
                        <input type="text" name="name" id="name" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white placeholder-gray-500" placeholder="Axborot texnologiyalari">
                    </div>
                    <!-- Branch select would go here if branches were passed in -->
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150">Qo'shish</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
