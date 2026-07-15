<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Training Attendance</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 25mm;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #000;
        }
        .outer {
            border: 2pt solid #000;
            padding: 8pt;
        }
        .inner {
            border: 1pt solid #000;
            padding: 10pt;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        td, th {
            padding: 5pt 6pt;
            vertical-align: middle;
            border: 0.5pt solid #000;
            font-size: 8pt;
        }
        .no-border { border: 0 !important; }
        .center { text-align: center; }
        .left { text-align: left; }
        .bold { font-weight: bold; }
        .logo { width: 110px; height: auto; }
        .title { font-size: 8pt; font-weight: bold; }
        .header-info td {
            border: 0.5pt solid #000;
            font-size: 8pt;
            padding: 2pt 4pt;
        }
        .signature-font {
            font-family: 'Times New Roman', serif;
            font-style: italic;
            font-size: 10pt;
        }
        .attendee-row td {
            height: 10pt;
        }
    </style>
</head>
<body>
    <div class="outer">
        <div class="inner">
            {{-- Top header --}}
            <table class="no-border" style="margin-bottom: 8pt;">
                <tr>
                    <td class="no-border" style="width: 50%; vertical-align: top;">
                        <img src="{{ public_path('images/Logo TSSB.jpeg') }}" alt="TSSB Logo" class="logo">
                    </td>
                    <td class="no-border" style="width: 50%; vertical-align: top;">
                        <table class="header-info" style="width: 100%;">
                            <tr>
                                <td class="center" style="width: 30%;">DOCUMENT</td>
                                <td class="center" style="width: 45%;">QR-C-5.5.3-002</td>
                                <td class="center" style="width: 25%;">ISSUE NO : 0</td>
                            </tr>
                            <tr>
                                <td class="center">PAGE</td>
                                <td class="center">1 OF 1 PAGES</td>
                                <td class="center">REV NO : 1</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            {{-- Title --}}
            <table class="no-border" style="margin-bottom: 8pt;">
                <tr>
                    <td class="no-border" style="width: 10%; vertical-align: top;">TITLE :</td>
                    <td class="no-border title" style="width: 90%;">TRAINING ATTENDANCE.</td>
                </tr>
            </table>

            {{-- Training details --}}
            <table class="no-border" style="margin-bottom: 12pt;">
                <tr>
                    <td class="no-border" style="width: 100%; padding-bottom: 6pt;">
                        <div class="small" style="margin-bottom: 3pt;">
                            <span class="bold">Re :</span> {{ $session->name }}
                        </div>
                        <div class="small" style="margin-bottom: 3pt;">
                            <span class="bold">Date :</span> {{ $session->training_date->format('d/m/Y') }}
                        </div>
                        <div class="small" style="margin-bottom: 3pt;">
                            <span class="bold">Time :</span> {{ $session->time_in->format('H:i') }} - {{ $session->time_out->format('H:i') }}
                        </div>
                        <div class="small">
                            <span class="bold">Venue :</span> {{ $session->venue }}
                        </div>
                    </td>
                </tr>
            </table>

            {{-- Attendance table --}}
            <table>
                <thead>
                    <tr>
                        <th class="center" style="width: 6%;">No</th>
                        <th class="center" style="width: 34%;">Name</th>
                        <th class="center" style="width: 12%;">Staff No</th>
                        <th class="center" style="width: 24%;">Signature</th>
                        <th class="center" style="width: 12%;">Time In</th>
                        <th class="center" style="width: 12%;">Time Out</th>
                    </tr>
                </thead>
                <tbody>
                    @php $maxRows = 9; @endphp
                    @for ($i = 0; $i < $maxRows; $i++)
                        @php $attendance = $attendances[$i] ?? null; @endphp
                        <tr class="attendee-row">
                            <td class="center">{{ $attendance ? $i + 1 : '' }}</td>
                            <td class="left" style="padding-left: 6pt;">{{ $attendance ? $attendance->user->name : '' }}</td>
                            <td class="center">{{ $attendance ? $attendance->staff_no : '' }}</td>
                            <td class="center signature-font">{{ $attendance ? $attendance->signature : '' }}</td>
                            <td class="center">{{ $attendance ? $session->time_in->format('H:i') : '' }}</td>
                            <td class="center">{{ $attendance ? $session->time_out->format('H:i') : '' }}</td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
