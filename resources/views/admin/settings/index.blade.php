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
                                                <input type="password"
                                                       name="config[{{ $config->key }}]"
                                                       id="config_{{ $config->key }}"
                                                       value="{{ $config->value }}"
                                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
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

    <x-help-button title="Bantuan Tetapan">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Tetapan Sistem</h3>
            <p class="mb-3">Konfigurasikan tetapan seluruh sistem seperti jam kerja, API Desknet, dan pilihan sinkronisasi.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Tips</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Jam Kerja</strong> — Tetapkan masa mula kerja lalai, rehat makan tengah hari, dan jam harian</li>
                <li><strong>API Desknet</strong> — Konfigurasikan kunci API dan URL asas untuk integrasi Desknet</li>
                <li>Klik "Simpan Semua Tetapan" untuk mengaplikasi perubahan</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
