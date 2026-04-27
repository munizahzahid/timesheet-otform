<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>[x-cloak] { display: none !important; }</style>
        @stack('styles')
    </head>
    <body class="font-sans antialiased" x-data="{ sidebarOpen: false }">
        <div class="min-h-screen bg-gray-100 lg:flex">
            {{-- Sidebar --}}
            @include('layouts.sidebar')

            {{-- Mobile overlay --}}
            <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
                 x-transition:enter="transition-opacity ease-linear duration-200"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-linear duration-200"
                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-black/50 z-20 lg:hidden"></div>

            {{-- Main Content Area --}}
            <div class="flex-1 lg:ml-60 flex flex-col min-h-screen">
                {{-- Top Bar (mobile hamburger + page heading) --}}
                <header class="bg-white shadow-sm sticky top-0 z-10">
                    <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 py-4">
                        {{-- Mobile menu button --}}
                        <button @click="sidebarOpen = !sidebarOpen"
                                class="lg:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>

                        {{-- Page Heading --}}
                        <div class="flex-1">
                            @isset($header)
                                {{ $header }}
                            @endisset
                        </div>

                        {{-- Top right: user name (desktop) --}}
                        <div class="hidden lg:flex items-center gap-3 text-sm text-gray-500">
                            <span>{{ Auth::user()->name }}</span>
                            <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-medium text-sm">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                        </div>
                    </div>
                </header>

                {{-- Page Content --}}
                <main class="flex-1 p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
