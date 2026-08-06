@extends('layouts.app')
@section('title', 'Kirish - Taskera')
@section('content')
<div class="flex items-center justify-center min-h-[60vh]">
    <div class="bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-700 w-full max-w-md">
        <h2 class="text-2xl font-bold text-center mb-6 text-white">Tizimga kirish</h2>
        <form action="/login" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="login" class="block text-sm font-medium text-gray-300">Email yoki Login</label>
                <input type="text" name="login" id="login" value="{{ old('login') }}" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">
                @error('login')
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
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input type="checkbox" name="remember" id="remember" class="h-4 w-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500">
                    <label for="remember" class="ml-2 block text-sm text-gray-300">Eslab qolish</label>
                </div>
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150">Kirish</button>
        </form>
        <p class="mt-4 text-center text-sm text-gray-400">
            Akkauntingiz yo'qmi? <a href="/register" class="text-indigo-400 hover:text-indigo-300">Ro'yxatdan o'tish</a>
        </p>
    </div>
</div>
@endsection
