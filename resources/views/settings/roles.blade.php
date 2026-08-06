@extends('layouts.app')
@section('title', 'Rollar - Taskera')
@section('content')
<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-white">Rollar</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Roles List -->
        <div class="lg:col-span-2">
            <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Nomi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Guard</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">Foydalanuvchilar</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($roles ?? [] as $role)
                            <tr class="hover:bg-gray-700/50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white font-medium">
                                    {{ $role->name }}
                                    @if($role->description)
                                    <p class="text-xs text-gray-400 font-normal mt-1">{{ $role->description }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $role->guard_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                    <span class="bg-gray-700 text-gray-300 py-1 px-3 rounded-full text-xs">{{ $role->users_count ?? 0 }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <form action="/settings/roles/{{ $role->id }}" method="POST" class="inline" onsubmit="return confirm('Rostdan ham o\'chirmoqchimisiz?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-300">O'chirish</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">Rollar mavjud emas.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Role Form -->
        <div class="space-y-6">
            <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-6">
                <h3 class="text-lg font-medium text-white mb-4">Yangi Rol Qo'shish</h3>
                
                <form action="/settings/roles" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300">Nomi</label>
                        <input type="text" name="name" id="name" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">
                    </div>
                    <div>
                        <label for="guard_name" class="block text-sm font-medium text-gray-300">Guard Nomi</label>
                        <input type="text" name="guard_name" id="guard_name" value="web" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-300">Tavsif (ixtiyoriy)</label>
                        <textarea name="description" id="description" rows="2" class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150">Qo'shish</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
