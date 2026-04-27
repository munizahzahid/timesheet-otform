@props(['title' => 'Help', 'content' => ''])

<div x-data="{ helpOpen: false }">
    {{-- Floating Help Button --}}
    <button @click="helpOpen = true"
            class="fixed bottom-6 right-6 z-50 w-12 h-12 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full shadow-lg flex items-center justify-center transition-all hover:scale-110 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            title="Help">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    </button>

    {{-- Help Slide-over Panel --}}
    <div x-show="helpOpen" x-cloak class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="help-panel-title" role="dialog" aria-modal="true">
        {{-- Backdrop --}}
        <div x-show="helpOpen"
             x-transition:enter="ease-in-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in-out duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @click="helpOpen = false"
             class="absolute inset-0 bg-black/30 transition-opacity"></div>

        {{-- Panel --}}
        <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
            <div x-show="helpOpen"
                 x-transition:enter="transform transition ease-in-out duration-300"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in-out duration-300"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full"
                 class="w-screen max-w-md">
                <div class="flex h-full flex-col bg-white shadow-xl">
                    {{-- Header --}}
                    <div class="bg-indigo-600 px-6 py-5">
                        <div class="flex items-center justify-between">
                            <h2 id="help-panel-title" class="text-lg font-semibold text-white flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $title }}
                            </h2>
                            <button @click="helpOpen = false" class="text-indigo-200 hover:text-white transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 overflow-y-auto px-6 py-5">
                        <div class="prose prose-sm max-w-none text-gray-600">
                            {{ $content }}
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="border-t px-6 py-4">
                        <p class="text-xs text-gray-400">Need more help? Contact your system administrator.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
