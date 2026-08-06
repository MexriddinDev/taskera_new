@extends('layouts.app')
@section('title', 'Yangi so\'rov - Taskera')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-white">Yangi so'rov yaratish</h1>
        <a href="/portal" class="text-gray-400 hover:text-white">&larr; Orqaga</a>
    </div>

    <div class="bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-700">
        <form action="/portal/request" method="POST" class="space-y-6">
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
                    <label for="category_id" class="block text-sm font-medium text-gray-300">Kategoriya</label>
                    <select name="category_id" id="category_id" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">
                        <option value="">Tanlang...</option>
                        @foreach($categories ?? [] as $category)
                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="priority_id" class="block text-sm font-medium text-gray-300">Prioritet</label>
                    <select name="priority_id" id="priority_id" required class="mt-1 block w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 text-white">
                        <option value="">Tanlang...</option>
                        @foreach($priorities ?? [] as $priority)
                            <option value="{{ $priority->id }}" {{ old('priority_id') == $priority->id ? 'selected' : '' }}>{{ $priority->name }}</option>
                        @endforeach
                    </select>
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
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg transition duration-150">Yuborish</button>
            </div>
        </form>
    </div>
</div>
@endsection
