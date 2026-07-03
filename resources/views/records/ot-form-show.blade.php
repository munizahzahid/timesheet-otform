<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('View OT Form') }} — {{ $otForm->user->name }}
            </h2>
            <a href="{{ route('records.ot-forms') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; {{ __('Back to list') }}</a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="bg-white shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-500">{{ $otForm->isExecutive() ? __('NAME') : __('NAMA') }}:</span>
                        <span class="ml-1 text-gray-900">{{ $otForm->user->name }}</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-500">{{ $otForm->isExecutive() ? __('STAFF NO') : __('NO. KT') }}:</span>
                        <span class="ml-1 text-gray-900">{{ $otForm->user->staff_no ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-500">{{ $otForm->isExecutive() ? __('DEPARTMENT') : __('JABATAN') }}:</span>
                        <span class="ml-1 text-gray-900">{{ $otForm->user->department->name ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-500">{{ $otForm->isExecutive() ? __('MONTH') : __('BULAN') }}:</span>
                        <span class="ml-1 text-gray-900">{{ strtoupper(DateTime::createFromFormat('!m', $otForm->month)->format('F')) }} {{ $otForm->year }}</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-500">{{ __('STATUS') }}:</span>
                        <span class="ml-1 font-semibold text-green-700">{{ $otForm->status_label }}</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-500">{{ __('TYPE') }}:</span>
                        <span class="ml-1 text-gray-900">{{ $otForm->form_type_label }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('OT Entries') }}</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border text-xs">
                            <thead>
                                <tr>
                                    <th class="border px-2 py-1 text-center">{{ $otForm->isExecutive() ? __('DATE') : __('TARIKH') }}</th>
                                    <th class="border px-2 py-1 text-left">{{ $otForm->isExecutive() ? __('PARTICULARS') : __('TUGAS') }}</th>
                                    <th class="border px-2 py-1 text-center bg-blue-50">{{ __('Plan Start') }}</th>
                                    <th class="border px-2 py-1 text-center bg-blue-50">{{ __('Plan End') }}</th>
                                    <th class="border px-2 py-1 text-center bg-blue-50">{{ __('Plan Total') }}</th>
                                    <th class="border px-2 py-1 text-center bg-green-50">{{ __('Actual Start') }}</th>
                                    <th class="border px-2 py-1 text-center bg-green-50">{{ __('Actual End') }}</th>
                                    <th class="border px-2 py-1 text-center bg-green-50">{{ __('Actual Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($otForm->entries as $entry)
                                    @php $isFilled = $entry->project_code_id || $entry->project_category || $entry->planned_start_time || $entry->actual_start_time; @endphp
                                    @if($isFilled)
                                    <tr>
                                        <td class="border px-2 py-1 text-center">
                                            {{ $otForm->isExecutive() ? $entry->entry_date->format('j/n/Y') : $entry->entry_date->day }}
                                        </td>
                                        <td class="border px-2 py-1">
                                            @if($entry->project_category)
                                                {{ $entry->project_category }}{{ $entry->manual_project_code_name ? ' - ' . $entry->manual_project_code_name : '' }}
                                            @else
                                                {{ $entry->projectCode ? $entry->projectCode->code : '' }}
                                                {{ $entry->project_name ? $entry->project_name : '' }}
                                            @endif
                                        </td>
                                        <td class="border px-2 py-1 text-center">{{ $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : '' }}</td>
                                        <td class="border px-2 py-1 text-center">{{ $entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : '' }}</td>
                                        <td class="border px-2 py-1 text-center font-medium">{{ $entry->planned_total_hours > 0 ? number_format($entry->planned_total_hours, 2) : '' }}</td>
                                        <td class="border px-2 py-1 text-center">{{ $entry->actual_start_time ? substr($entry->actual_start_time, 0, 5) : '' }}</td>
                                        <td class="border px-2 py-1 text-center">{{ $entry->actual_end_time ? substr($entry->actual_end_time, 0, 5) : '' }}</td>
                                        <td class="border px-2 py-1 text-center font-medium">{{ $entry->actual_total_hours > 0 ? number_format($entry->actual_total_hours, 2) : '' }}</td>
                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="4" class="border px-2 py-1 text-right font-semibold">{{ __('TOTAL') }}:</td>
                                    <td class="border px-2 py-1 text-center font-bold">{{ number_format($otForm->entries->sum('planned_total_hours'), 2) }}</td>
                                    <td colspan="2" class="border"></td>
                                    <td class="border px-2 py-1 text-center font-bold">{{ number_format($otForm->entries->sum('actual_total_hours'), 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            @if($otForm->hr_remarks)
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('HR Correction Notes') }}</h3>
                        <div class="space-y-2">
                            <p class="text-xs text-gray-500">
                                {{ __('Edited by') }} {{ $otForm->hrEditor?->name ?? __('HR') }} {{ __('on') }} {{ $otForm->hr_edited_at?->format('d/m/Y H:i') }}
                            </p>
                            <div class="text-sm text-gray-800 bg-yellow-50 border border-yellow-200 rounded-md p-3 space-y-3 leading-relaxed">
                                @foreach(explode("\n\n", $otForm->hr_remarks) as $dateBlock)
                                    <div>{!! nl2br(e($dateBlock)) !!}</div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Approval Trail') }}</h3>
                    <x-approval-stamps :stamps="$approvalStamps" />
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Export Options') }}</h3>
                    <div class="flex items-center gap-4">
                        <a href="{{ route('ot-forms.export-excel', $otForm) }}"
                           class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #16a34a !important; color: white !important;">
                            {{ __('Download Excel') }}
                        </a>
                        <a href="{{ route('ot-forms.export-pdf', $otForm) }}"
                           class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #dc2626 !important; color: white !important;">
                            {{ __('Download PDF') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
