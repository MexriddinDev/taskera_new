@extends('layouts.app')
@section('title', "Ro'yxatdan o'tish - Taskera")
@section('content')
<div class="flex items-center justify-center min-h-[60vh]">
    <div class="bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-700 w-full max-w-md">
        <h2 class="text-2xl font-bold text-center mb-6 text-white">Ro'yxatdan o'tish</h2>
        <form action="/register" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="username" class="block text-sm font-medium text-gray-300">Foydalanuvchi nomi</label>
                <input type="text" name="username" id="username" value="{{ old('username') }}" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">
                @error('username')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-300">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">
                @error('email')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-300">Parol</label>
                <input type="password" name="password" id="password" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">
                @error('password')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-300">Parolni tasdiqlang</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150">Ro'yxatdan o'tish</button>
        </form>
        <p class="mt-4 text-center text-sm text-gray-400">
            Akkauntingiz bormi? <a href="/login" class="text-indigo-400 hover:text-indigo-300">Kirish</a>
        </p>
    </div>
</div>
@endsection
