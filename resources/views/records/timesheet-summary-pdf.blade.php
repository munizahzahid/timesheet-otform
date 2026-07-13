<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Timesheet Summary</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 7pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5px solid #333; padding: 2px 3px; text-align: center; }
        th { background: #f0f0f0; font-weight: bold; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .header-title { text-align: center; font-size: 10pt; font-weight: bold; }
        .sub-header { text-align: center; font-size: 8pt; margin-bottom: 5px; }
        .admin-job { background: #fafafa; }
        .total-row { background: #e0e0e0; font-weight: bold; }
        .summary-external { color: #0008f0; font-weight: bold; }
        .summary-working { color: #0008f0; font-weight: bold; }
        .summary-available { color: #dc2626; font-weight: bold; }
        .summary-overtime { color: #0008f0; font-weight: bold; }
        .negative { color: #dc2626; }
        .project-name { font-size: 6pt; color: #666; }
        .project-last-row td { border-bottom: 1.5px solid #000; }
        .admin-blank-col { border-top: none; border-right: none; border-bottom: none; border-left: 0.5px solid #000; background: #fff; }
    </style>
</head>
<body>
    <table style="width: 100%; border: none; margin-bottom: 8px;">
        <tr style="border: none;">
            <td style="width: 25%; border: none; text-align: left; font-size: 8pt; font-weight: bold;">MONTH: {{ strtoupper(\DateTime::createFromFormat('!m', $month)->format('M')) }}-{{ substr((string) $year, -2) }}</td>
            <td style="width: 50%; border: none; text-align: center; font-size: 10pt; font-weight: bold;">MONTHLY TIMESHEET SUMMARY - {{ strtoupper(\App\Models\User::CATEGORIES[$category] ?? $category) }}</td>
            <td style="width: 25%; border: none;"></td>
        </tr>
    </table>

    @php
        $staffIds = $staff->pluck('id')->toArray();
        $rowHourTypes = [
            ['key' => 'normal_nc', 'group' => 'NORMAL', 'cost' => 'NC', 'group_span' => 2],
            ['key' => 'normal_cobq', 'group' => '', 'cost' => 'COBQ', 'group_span' => 0],
            ['key' => 'ot_nc', 'group' => 'OT', 'cost' => 'NC', 'group_span' => 2],
            ['key' => 'ot_cobq', 'group' => '', 'cost' => 'COBQ', 'group_span' => 0],
        ];

        $prefixes = ['MUHAMMAD ', 'MOHAMMAD ', 'MOHAMMED ', 'MUHAMAD ', 'MOHAMED ', 'MOHAMAD ', 'MOHD ', 'MUHD ', 'NURUL ', 'NUR ', 'SITI '];
        $separators = [' BIN ', ' BINTI ', ' B ', ' BT '];
        $displayStaff = [];
        foreach ($staff as $user) {
            $name = strtoupper($user->name);
            foreach ($prefixes as $prefix) {
                if (strpos($name, $prefix) === 0) {
                    $name = substr($name, strlen($prefix));
                    break;
                }
            }
            foreach ($separators as $sep) {
                $pos = strpos($name, $sep);
                if ($pos !== false) {
                    $name = substr($name, 0, $pos);
                    break;
                }
            }
            $displayStaff[] = ['id' => $user->id, 'name' => trim($name)];
        }
        while (count($displayStaff) < 13) {
            $displayStaff[] = ['id' => null, 'name' => ''];
        }
        $staffCount = count($displayStaff);
    @endphp

    {{-- Combined Admin + Project Table --}}
    <table>
        <thead>
            <tr>
                <th rowspan="3" style="width: 3%;">NO</th>
                <th rowspan="3" colspan="3" style="width: 24%;">ADMIN JOB</th>
                <th colspan="{{ $staffCount }}" style="border-right: 1px solid #000;">HOURS</th>
                <th rowspan="3" style="width: 7%;">TOTAL</th>
                <th rowspan="3" class="admin-blank-col" style="width: 7%;"></th>
            </tr>
            <tr>
                <th colspan="{{ $staffCount }}" style="border-right: 1px solid #000;">{{ strtoupper(\App\Models\User::CATEGORIES[$category] ?? $category) }}</th>
            </tr>
            <tr>
                @foreach($displayStaff as $user)
                    <th style="width: {{ 60 / max($staffCount, 1) }}%;{{ $loop->last ? ' border-right: 1px solid #000;' : '' }}">{{ $user['name'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($adminTypes as $type => $label)
                <tr class="admin-job">
                    <td>{{ $loop->iteration }}</td>
                    <td colspan="3" class="text-left">{{ $label }}</td>
                    @php $rowTotal = 0; @endphp
                    @foreach($displayStaff as $user)
                        @php $value = $user['id'] ? ($adminHours[$type][$user['id']] ?? 0) : 0; $rowTotal += $value; @endphp
                        <td>{{ $value > 0 ? number_format($value, 1) : '' }}</td>
                    @endforeach
                    <td class="text-right">{{ $rowTotal > 0 ? number_format($rowTotal, 2) : '' }}</td>
                    <td class="admin-blank-col"></td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td></td>
                <td colspan="3" class="text-left">TOTAL ADMIN JOB</td>
                @php $grandAdminTotal = 0; @endphp
                @foreach($displayStaff as $user)
                    @php
                        $userAdminTotal = 0;
                        if ($user['id']) {
                            foreach ($adminHours as $type => $hours) {
                                $userAdminTotal += $hours[$user['id']] ?? 0;
                            }
                        }
                        $grandAdminTotal += $userAdminTotal;
                    @endphp
                    <td>{{ $userAdminTotal > 0 ? number_format($userAdminTotal, 1) : '' }}</td>
                @endforeach
                <td class="text-right">{{ $grandAdminTotal > 0 ? number_format($grandAdminTotal, 2) : '' }}</td>
                <td class="admin-blank-col"></td>
            </tr>

            {{-- Project Code header --}}
            <tr>
                <th rowspan="2" style="width: 3%;">NO</th>
                <th rowspan="2" style="width: 12%;">PROJECT CODE</th>
                <th colspan="2" style="width: 12%;">TIME / COST</th>
                <th colspan="{{ $staffCount }}" rowspan="2" style="width: 60%;"></th>
                <th rowspan="2" style="width: 7%;">TOTAL</th>
                <th rowspan="2" style="width: 7%;"></th>
            </tr>
            <tr>
                <th colspan="2"></th>
            </tr>

            {{-- Project rows --}}
            @forelse($projects as $key => $project)
                @php
                    $projectTotal = 0;
                    foreach ($displayStaff as $user) {
                        if ($user['id']) {
                            $projectTotal += ($project['hours'][$user['id']]['normal_nc'] ?? 0)
                                + ($project['hours'][$user['id']]['normal_cobq'] ?? 0)
                                + ($project['hours'][$user['id']]['ot_nc'] ?? 0)
                                + ($project['hours'][$user['id']]['ot_cobq'] ?? 0);
                        }
                    }
                @endphp
                @foreach($rowHourTypes as $rIdx => $rowType)
                    <tr class="{{ $rIdx === 3 ? 'project-last-row' : '' }}">
                        @if($rIdx === 0)
                            <td rowspan="4" style="border-bottom: 1.5px solid #000;">{{ $loop->parent->iteration }}</td>
                            <td rowspan="4" class="text-left" style="border-bottom: 1.5px solid #000;">
                                <strong>{{ $project['code'] }}</strong><br>
                                <span class="project-name">{{ $project['name'] }}</span>
                            </td>
                        @endif
                        @if($rowType['group_span'] > 0)
                            <td rowspan="{{ $rowType['group_span'] }}" style="{{ $rIdx === 2 ? 'border-bottom: 1.5px solid #000;' : '' }}">{{ $rowType['group'] }}</td>
                        @endif
                        <td>{{ $rowType['cost'] }}</td>
                        @php $rowTotal = 0; @endphp
                        @foreach($displayStaff as $user)
                            @php $value = $user['id'] ? ($project['hours'][$user['id']][$rowType['key']] ?? 0) : 0; $rowTotal += $value; @endphp
                            <td>{{ $value > 0 ? number_format($value, 1) : '' }}</td>
                        @endforeach
                        <td class="text-right">{{ $rowTotal > 0 ? number_format($rowTotal, 2) : '' }}</td>
                        @if($rIdx === 0)
                            <td rowspan="4" style="border-bottom: 1.5px solid #000;">{{ $projectTotal > 0 ? number_format($projectTotal, 2) : '' }}</td>
                        @endif
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="{{ $staffCount + 6 }}" style="text-align: center; padding: 10px;">No project data found for this category and month.</td>
                </tr>
            @endforelse
            <tr class="summary-external">
                <td colspan="4" class="text-left">TOTAL EXTERNAL PROJECT</td>
                @php $grandExternal = 0; @endphp
                @foreach($displayStaff as $user)
                    @php $value = $user['id'] ? ($summary[$user['id']]['total_external_project'] ?? 0) : 0; $grandExternal += $value; @endphp
                    <td>{{ $value > 0 ? number_format($value, 1) : '' }}</td>
                @endforeach
                <td class="text-right">{{ $grandExternal > 0 ? number_format($grandExternal, 2) : '' }}</td>
                <td class="text-right">{{ $grandExternal > 0 ? number_format($grandExternal, 2) : '' }}</td>
            </tr>
            <tr class="summary-working">
                <td colspan="4" class="text-left">TOTAL WORKING HOURS</td>
                @php $grandWorking = 0; @endphp
                @foreach($displayStaff as $user)
                    @php $value = $user['id'] ? ($summary[$user['id']]['total_working_hours'] ?? 0) : 0; $grandWorking += $value; @endphp
                    <td>{{ $value > 0 ? number_format($value, 1) : '' }}</td>
                @endforeach
                <td class="text-right">{{ $grandWorking > 0 ? number_format($grandWorking, 2) : '' }}</td>
                <td class="admin-blank-col"></td>
            </tr>
            <tr class="summary-available">
                <td colspan="4" class="text-left">HOURS AVAILABLE</td>
                @php $grandAvailable = 0; @endphp
                @foreach($displayStaff as $user)
                    @php
                        $value = $user['id'] ? ($summary[$user['id']]['hours_available'] ?? 0) : 0;
                        $grandAvailable += $value;
                    @endphp
                    <td>{{ number_format($value, 1) }}</td>
                @endforeach
                <td class="text-right">{{ number_format($grandAvailable, 2) }}</td>
                <td class="admin-blank-col"></td>
            </tr>
            <tr class="summary-overtime">
                <td colspan="4" class="text-left">OVERTIME</td>
                @php $grandOvertime = 0; @endphp
                @foreach($displayStaff as $user)
                    @php
                        $userWorking = $user['id'] ? ($summary[$user['id']]['total_working_hours'] ?? 0) : 0;
                        $userAvailable = $user['id'] ? ($summary[$user['id']]['hours_available'] ?? 0) : 0;
                        $value = $userWorking - $userAvailable;
                        $grandOvertime += $value;
                    @endphp
                    <td class="{{ $value < 0 ? 'negative' : '' }}">{{ number_format($value, 1) }}</td>
                @endforeach
                <td class="text-right {{ $grandOvertime < 0 ? 'negative' : '' }}">{{ number_format($grandOvertime, 2) }}</td>
                <td class="admin-blank-col"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
