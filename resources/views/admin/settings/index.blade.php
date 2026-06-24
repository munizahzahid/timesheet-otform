<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('System Settings') }}</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto">

            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                @foreach($groups as $groupName => $keys)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $groupName }}</h3>
                            <div class="space-y-4">
                                @foreach($configs->whereIn('key', $keys) as $config)
                                    <div class="grid grid-cols-3 gap-4 items-center">
                                        <div>
                                            <label for="config_{{ $config->key }}" class="block text-sm font-medium text-gray-700">
                                                {{ str_replace('_', ' ', ucwords(str_replace('_', ' ', $config->key))) }}
                                            </label>
                                            <p class="text-xs text-gray-500">{{ $config->description }}</p>
                                        </div>
                                        <div class="col-span-2">
                                            @if($config->key === 'desknet_api_key')
                                                <div class="relative">
                                                    <input type="password"
                                                           name="config[{{ $config->key }}]"
                                                           id="config_{{ $config->key }}"
                                                           value="{{ $config->value }}"
                                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm pr-10">
                                                    <button type="button" onclick="togglePassword('config_{{ $config->key }}', this)" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            @elseif($config->key === 'desknet_sync_enabled')
                                                <select name="config[{{ $config->key }}]"
                                                        id="config_{{ $config->key }}"
                                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                    <option value="1" {{ $config->value == '1' ? 'selected' : '' }}>Enabled</option>
                                                    <option value="0" {{ $config->value == '0' ? 'selected' : '' }}>Disabled</option>
                                                </select>
                                            @else
                                                <input type="text"
                                                       name="config[{{ $config->key }}]"
                                                       id="config_{{ $config->key }}"
                                                       value="{{ $config->value }}"
                                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md text-sm hover:bg-indigo-700">
                        Save All Settings
                    </button>
                </div>
            </form>
    </div>

    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('svg');

            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
            } else {
                input.type = 'password';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
            }
        }
    </script>

    <x-help-button title="Settings Help">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">System Settings</h3>
            <p class="mb-3">Configure system-wide settings such as work hours, Desknet API, and sync options.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Tips</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Work Hours</strong> — Set default work start time, lunch break, and daily hours</li>
                <li><strong>Desknet API</strong> — Configure API key and base URL for Desknet integration</li>
                <li>Click <strong>"Save All Settings"</strong> button to apply changes</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
