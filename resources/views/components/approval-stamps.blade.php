{{--
    Approval Stamps Component
    Usage: <x-approval-stamps :stamps="$stamps" />

    $stamps = [
        [
            'label'    => 'Supported by',    // Header label
            'priority' => 1,                 // Priority number (displayed in header)
            'code'     => 'SPRT',            // Stamp abbreviation
            'status'   => 'approved',        // approved | pending | empty
            'date'     => '01/21',           // Date string (MM/DD)
            'name'     => 'JOHN DOE',        // Approver name
            'role'     => 'Staff',           // Role/title
        ],
        ...
    ];
--}}

@props(['stamps' => []])

<div class="w-full">
    <div class="flex flex-wrap justify-center gap-0">
        @foreach($stamps as $stamp)
            <div class="flex-1 min-w-[120px] max-w-[160px] border border-gray-300 {{ !$loop->last ? 'border-r-0' : '' }}"
                 style="{{ !$loop->last ? 'border-right: none;' : '' }}">
                {{-- Header --}}
                <div class="bg-gray-50 border-b border-gray-300 px-2 py-1.5 text-center">
                    <span class="text-[10px] font-semibold text-gray-700 uppercase tracking-wide">
                        {{ $stamp['label'] ?? 'Approval' }}
                    </span>
                </div>

                {{-- Stamp Area --}}
                <div class="px-3 py-3 flex flex-col items-center justify-center min-h-[110px]">
                    @if(($stamp['status'] ?? 'empty') === 'approved')
                        {{-- Circular Stamp --}}
                        <div class="relative w-[72px] h-[72px] flex items-center justify-center">
                            {{-- Outer ring --}}
                            <div class="absolute inset-0 rounded-full border-[2.5px] border-red-600"></div>
                            {{-- Inner ring --}}
                            <div class="absolute inset-[4px] rounded-full border-[1px] border-red-600"></div>
                            {{-- TSSB curved top text --}}
                            <svg class="absolute inset-0 w-full h-full" viewBox="0 0 72 72">
                                <defs>
                                    <path id="topArc-{{ $loop->index }}" d="M 12,36 a 24,24 0 1,1 48,0" fill="none"/>
                                </defs>
                                <text fill="#dc2626" font-size="7" font-weight="bold" font-family="Arial, sans-serif" letter-spacing="3">
                                    <textPath href="#topArc-{{ $loop->index }}" startOffset="50%" text-anchor="middle">TSSB</textPath>
                                </text>
                            </svg>
                            {{-- Center content --}}
                            <div class="flex flex-col items-center justify-center z-10">
                                <span class="text-red-600 font-bold text-[11px] leading-none tracking-wider">{{ $stamp['code'] ?? 'APRV' }}</span>
                                <span class="text-red-600 font-bold text-[10px] leading-tight mt-0.5">{{ $stamp['date'] ?? '' }}</span>
                            </div>
                            {{-- Bottom curved text (decorative stars) --}}
                            <svg class="absolute inset-0 w-full h-full" viewBox="0 0 72 72">
                                <defs>
                                    <path id="bottomArc-{{ $loop->index }}" d="M 12,36 a 24,24 0 1,0 48,0" fill="none"/>
                                </defs>
                                <text fill="#dc2626" font-size="6" font-family="Arial, sans-serif" letter-spacing="2">
                                    <textPath href="#bottomArc-{{ $loop->index }}" startOffset="50%" text-anchor="middle">&#9733; &#9733; &#9733;</textPath>
                                </text>
                            </svg>
                        </div>

                        {{-- Name below stamp --}}
                        <div class="mt-2 text-center">
                            <p class="text-[10px] font-semibold text-red-700 uppercase leading-tight">{{ $stamp['name'] ?? '' }}</p>
                            @if(!empty($stamp['role']))
                                <p class="text-[8px] text-red-500 uppercase">{{ $stamp['role'] }}</p>
                            @endif
                        </div>

                    @elseif(($stamp['status'] ?? 'empty') === 'rejected')
                        {{-- Rejected stamp --}}
                        <div class="relative w-[72px] h-[72px] flex items-center justify-center">
                            <div class="absolute inset-0 rounded-full border-[2.5px] border-red-400 opacity-60"></div>
                            <div class="absolute inset-[4px] rounded-full border-[1px] border-red-400 opacity-60"></div>
                            <div class="flex flex-col items-center justify-center z-10">
                                <span class="text-red-500 font-bold text-[10px] leading-none">RJCT</span>
                                <span class="text-red-500 font-bold text-[9px] leading-tight mt-0.5">{{ $stamp['date'] ?? '' }}</span>
                            </div>
                        </div>
                        <div class="mt-2 text-center">
                            <p class="text-[10px] font-semibold text-red-500 uppercase leading-tight">{{ $stamp['name'] ?? '' }}</p>
                            <p class="text-[8px] text-red-400 uppercase">Rejected</p>
                        </div>

                    @elseif(($stamp['status'] ?? 'empty') === 'pending')
                        {{-- Pending state --}}
                        <div class="relative w-[72px] h-[72px] flex items-center justify-center opacity-30">
                            <div class="absolute inset-0 rounded-full border-[2px] border-dashed border-gray-400"></div>
                            <div class="flex flex-col items-center justify-center z-10">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <p class="mt-2 text-[9px] text-gray-400 font-medium uppercase">Pending</p>

                    @else
                        {{-- Empty state --}}
                        <div class="relative w-[72px] h-[72px] flex items-center justify-center opacity-15">
                            <div class="absolute inset-0 rounded-full border-[2px] border-gray-300"></div>
                        </div>
                        <p class="mt-2 text-[9px] text-gray-300 font-medium">—</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
