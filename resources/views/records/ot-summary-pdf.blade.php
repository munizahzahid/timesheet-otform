<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>OT Summary</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 7pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5px solid #333; padding: 2px 3px; text-align: center; }
        th { background: #f0f0f0; font-weight: bold; }
        .text-left { text-align: left; }
        .project-name { font-size: 6pt; color: #666; }
        .total-row { background: #dbeafe; font-weight: bold; }
    </style>
</head>
<body>
    <table style="width: 100%; border: none; margin-bottom: 8px;">
        <tr style="border: none;">
            <td style="width: 25%; border: none; text-align: left; font-size: 8pt; font-weight: bold;">MONTH: {{ strtoupper(\DateTime::createFromFormat('!m', $month)->format('M')) }}-{{ substr((string) $year, -2) }}</td>
            <td style="width: 50%; border: none; text-align: center; font-size: 10pt; font-weight: bold;">OT SUMMARY - {{ $category === 'all' ? 'ALL STAFF' : strtoupper(\App\Models\User::CATEGORIES[$category] ?? $category) }}</td>
            <td style="width: 25%; border: none;"></td>
        </tr>
    </table>

    @php
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

        $grandTotal = 0;
        foreach ($totals as $total) {
            $grandTotal += $total;
        }
    @endphp

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">NO</th>
                <th class="text-left" style="width: 22%;">PROJECT</th>
                @foreach ($displayStaff as $user)
                    <th>{{ $user['name'] }}</th>
                @endforeach
                <th style="width: 9%;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @php $idx = 1; @endphp
            @forelse ($projects as $project)
                <tr>
                    <td>{{ $idx++ }}</td>
                    <td class="text-left">
                        <strong>{{ $project['code'] }}</strong><br>
                        <span class="project-name">{{ $project['name'] }}</span>
                    </td>
                    @php $rowTotal = 0; @endphp
                    @foreach ($displayStaff as $user)
                        @php
                            $value = $user['id'] ? ($project['hours'][$user['id']] ?? 0) : 0;
                            $rowTotal += $value;
                        @endphp
                        <td>{{ $value != 0 ? number_format($value, 2) : '' }}</td>
                    @endforeach
                    <td><strong>{{ number_format($rowTotal, 2) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($displayStaff) + 3 }}" style="text-align: center; padding: 10px;">No OT data found for this period.</td>
                </tr>
            @endforelse

            <tr class="total-row">
                <td></td>
                <td class="text-left">TOTAL OT HOURS</td>
                @foreach ($displayStaff as $user)
                    @php $userTotal = $user['id'] ? ($totals[$user['id']] ?? 0) : 0; @endphp
                    <td>{{ $userTotal != 0 ? number_format($userTotal, 2) : '' }}</td>
                @endforeach
                <td>{{ number_format($grandTotal, 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
