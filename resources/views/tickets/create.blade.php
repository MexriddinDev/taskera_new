@extends('layouts.app')
@section('title', 'Yangi Ticket - Taskera')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-white">Yangi Ticket Yaratish</h1>
        <a href="/tickets" class="text-gray-400 hover:text-white">&larr; Orqaga</a>
    </div>

    <div class="bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-700">
        <form action="/tickets" method="POST" class="space-y-6">
            @csrf
            
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-300">Mavzu</label>
                <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">
                @error('subject')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-300">Kategoriya ID</label>
                    <input type="number" name="category_id" id="category_id" value="{{ old('category_id') }}" class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">
                    @error('category_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="priority_id" class="block text-sm font-medium text-gray-300">Prioritet ID</label>
                    <input type="number" name="priority_id" id="priority_id" value="{{ old('priority_id') }}" class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">
                    @error('priority_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-300">Tafsilotlar</label>
                <textarea name="description" id="description" rows="5" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">{{ old('description') }}</textarea>
                @error('description')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg transition duration-150">Yaratish</button>
            </div>
        </form>
    </div>
</div>
@endsection
