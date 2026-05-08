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
        <style>
            [x-cloak] { display: none !important; }
            @media (min-width: 1024px) {
                #main-content { margin-left: 16rem !important; }
            }
        </style>
        @stack('styles')
    </head>
    <body class="font-sans antialiased bg-gray-50" x-data="{ sidebarOpen: false }">
        {{-- Sidebar (fixed position) --}}
        @include('layouts.sidebar')

        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/50 z-20 lg:hidden"></div>

        {{-- Main Content Area (pushed right on desktop) --}}
        <div id="main-content" class="min-h-screen flex flex-col">
                {{-- Top Navbar --}}
                <header class="bg-white border-b border-gray-200 relative z-20 shadow-sm">
                    <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 py-3">
                        <div class="flex items-center gap-3">
                            {{-- Mobile menu button --}}
                            <button @click="sidebarOpen = !sidebarOpen"
                                    class="lg:hidden inline-flex items-center justify-center p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>

                            {{-- Back button --}}
                            @if(request()->header('Referer') && !request()->routeIs('dashboard'))
                                <a href="{{ request()->header('Referer') }}" 
                                   class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                    </svg>
                                    Back
                                </a>
                            @endif

                            {{-- Home button --}}
                            <a href="{{ route('dashboard') }}"
                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                Home
                            </a>
                        </div>

                        {{-- Optional Action Buttons --}}
                        @isset($actionButtons)
                            <div class="flex items-center gap-2">
                                {{ $actionButtons }}
                            </div>
                        @endisset

                        {{-- User info --}}
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-900">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-gray-500">{{ Auth::user()->designation ?? Auth::user()->email }}</p>
                            </div>
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-700 to-blue-900 rounded-full flex items-center justify-center text-white font-bold shadow-lg text-sm">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                        </div>
                    </div>
                </header>

            {{-- Page Content --}}
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-[1920px] mx-auto w-full">
                {{-- Page Title Section --}}
                @isset($pageTitle)
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $pageTitle }}</h1>
                    </div>
                @elseif(isset($header))
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $header }}</h1>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
        @stack('scripts')
    </body>
</html>
