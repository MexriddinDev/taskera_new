<!DOCTYPE html>
<html lang="uz" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Taskera')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        secondary: '#1e293b',
                    }
                }
            }
        }
    </script>
    @stack('styles')
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex flex-col">
    <!-- Navbar -->
    <nav class="bg-gray-800 border-b border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="text-xl font-bold text-indigo-500">Taskera</a>
                    @auth
                    <div class="hidden md:block ml-10">
                        <div class="flex items-baseline space-x-4">
                            <a href="/dashboard" class="hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                            <a href="/portal" class="hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Portal</a>
                            <a href="/tickets/open" class="hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Ochiq Ticketlar</a>
                            <a href="/tickets/my" class="hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Mening</a>
                            <a href="/tickets/kanban" class="hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Kanban</a>
                            <a href="/analytics" class="hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Analitika</a>
                            <a href="/settings/roles" class="hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Rollar</a>
                            <a href="/organization/departments" class="hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Bo'limlar</a>
                        </div>
                    </div>
                    @endauth
                </div>
                <div>
                    @auth
                        <div class="flex items-center space-x-4">
                            <a href="/profile" class="text-sm font-medium hover:text-indigo-400">Profil</a>
                            <form action="/logout" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-sm font-medium text-red-500 hover:text-red-400">Chiqish</button>
                            </form>
                        </div>
                    @else
                        <div class="flex items-center space-x-4">
                            <a href="/login" class="text-sm font-medium hover:text-indigo-400">Kirish</a>
                            <a href="/register" class="text-sm font-medium bg-indigo-600 hover:bg-indigo-700 px-3 py-2 rounded-md transition">Ro'yxatdan o'tish</a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
        @if (session('success'))
            <div class="bg-green-600/20 border border-green-500 text-green-400 px-4 py-3 rounded-xl shadow-lg" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="bg-red-600/20 border border-red-500 text-red-400 px-4 py-3 rounded-xl shadow-lg" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif
    </div>

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 border-t border-gray-700 py-6 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center text-sm text-gray-400">
            &copy; {{ date('Y') }} Taskera. Barcha huquqlar himoyalangan.
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
