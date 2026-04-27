<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $title }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="mb-6 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Excel Preview</h3>
                <div class="flex gap-3">
                    <a href="{{ $backUrl }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-gray-700">
                        Back
                    </a>
                    <a href="{{ $downloadUrl }}"
                       class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-green-700">
                        Download Excel
                    </a>
                </div>
            </div>

            <div class="border border-gray-300 rounded-lg overflow-hidden">
                <iframe
                    src="{{ asset('storage/temp/' . basename($filePath)) }}"
                    class="w-full h-[80vh] border-0"
                    title="Excel Preview"
                ></iframe>
            </div>

            <p class="mt-4 text-sm text-gray-500">
                Preview generated at {{ now()->format('d/m/Y H:i') }}. If the preview looks correct, click "Download Excel" to save the file.
            </p>
        </div>
    </div>
</x-app-layout>
