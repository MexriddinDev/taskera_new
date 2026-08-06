@extends('layouts.app')
@section('title', 'Profil - Taskera')
@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-white mb-6">Mening Profilim</h1>

    <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 overflow-hidden">
        <div class="p-8 border-b border-gray-700 flex items-center space-x-6">
            <div class="w-24 h-24 bg-indigo-600 rounded-full flex items-center justify-center text-white text-3xl font-bold uppercase shadow-inner">
                {{ substr($user->username ?? 'U', 0, 1) }}
            </div>
            <div>
                <h2 class="text-2xl font-bold text-white">{{ $user->username ?? 'Foydalanuvchi' }}</h2>
                <p class="text-gray-400">{{ $user->email ?? 'email@example.com' }}</p>
                <div class="mt-2">
                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">
                        {{ $role->name ?? 'Rol belgilanmagan' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="p-8">
            <h3 class="text-lg font-medium text-white mb-4">Xodim Ma'lumotlari</h3>
            
            @if(isset($employee))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gray-700/30 p-4 rounded-lg border border-gray-700">
                    <p class="text-sm text-gray-400 mb-1">Filial</p>
                    <p class="text-white font-medium">{{ $employee->branch->name ?? 'Kiritilmagan' }}</p>
                </div>
                <div class="bg-gray-700/30 p-4 rounded-lg border border-gray-700">
                    <p class="text-sm text-gray-400 mb-1">Bo'lim</p>
                    <p class="text-white font-medium">{{ $employee->department->name ?? 'Kiritilmagan' }}</p>
                </div>
                <div class="bg-gray-700/30 p-4 rounded-lg border border-gray-700">
                    <p class="text-sm text-gray-400 mb-1">Lavozim</p>
                    <p class="text-white font-medium">{{ $employee->position->name ?? 'Kiritilmagan' }}</p>
                </div>
            </div>
            @else
            <div class="bg-yellow-500/10 border border-yellow-500/20 p-4 rounded-lg">
                <p class="text-yellow-400 text-sm">Xodim ma'lumotlari topilmadi. Profilingiz hali to'liq to'ldirilmagan bo'lishi mumkin.</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
