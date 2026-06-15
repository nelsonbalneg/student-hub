@php
    $logoPath = $campus['logo_url'] ?? null;
    $logoSrc = null;

    if ($logoPath && is_file($logoPath)) {
        $mimeType = mime_content_type($logoPath) ?: 'image/png';
        $logoSrc = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($logoPath));
    }

    $formatUnit = function ($value): string {
        $number = (float) $value;

        if ($number <= 0) {
            return '-';
        }

        return floor($number) === $number ? (string) (int) $number : rtrim(rtrim(number_format($number, 2), '0'), '.');
    };

    $profileValue = function (string $key, string $fallback = '-') use ($profile): string {
        if (!is_array($profile)) {
            return $fallback;
        }

        $value = data_get($profile, $key);

        return filled($value) ? trim((string) $value) : $fallback;
    };
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Curriculum - {{ $studentNo }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.32;
        }

        .sheet {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            overflow: hidden;
            background: #ffffff;
            padding: 14mm 14mm 35mm;
        }

        .sheet::before {
            content: "STUDENTHUB";
            position: fixed;
            left: 50%;
            top: 52%;
            z-index: 0;
            transform: translate(-50%, -50%) rotate(-32deg);
            color: rgba(15, 23, 42, 0.055);
            font-size: 58px;
            font-weight: 800;
            letter-spacing: 0.18em;
            white-space: nowrap;
            pointer-events: none;
        }

        .content {
            position: relative;
            z-index: 1;
        }

        .header {
            display: grid;
            grid-template-columns: 82px 1fr 82px;
            gap: 12px;
            align-items: center;
            text-align: center;
        }

        .logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
        }

        .logo-placeholder {
            display: grid;
            place-items: center;
            width: 72px;
            height: 72px;
            border: 1px solid #cbd5e1;
            border-radius: 50%;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            text-align: center;
        }

        .republic {
            font-size: 11px;
        }

        .university {
            margin-top: 2px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .campus {
            margin-top: 2px;
            font-size: 11px;
        }

        h1 {
            margin: 12px 0 10px;
            text-align: center;
            font-size: 15px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .student-name {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
        }

        .student-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 16px;
            margin-top: 8px;
        }

        .details {
            display: grid;
            grid-template-columns: 92px 10px 1fr;
            row-gap: 4px;
        }

        .label {
            color: #334155;
            font-weight: 700;
        }

        .value {
            color: #111827;
            font-weight: 700;
        }

        .summary {
            width: 58%;
            margin-left: auto;
            margin-top: 10px;
            display: grid;
            grid-template-columns: 1fr 12px 80px;
            row-gap: 4px;
        }

        .year {
            margin-top: 12px;
            page-break-inside: avoid;
        }

        .year-title {
            margin: 0;
            border: 1px solid #111827;
            border-bottom: 0;
            padding: 5px 6px;
            text-align: center;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .semester {
            page-break-inside: avoid;
        }

        .semester-heading {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border: 1px solid #111827;
            border-bottom: 0;
            padding: 5px 6px;
            font-size: 10px;
            font-weight: 800;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #111827;
            padding: 5px 6px;
            vertical-align: top;
        }

        th {
            text-align: center;
            font-size: 10px;
            text-transform: uppercase;
        }

        td {
            font-size: 10.5px;
        }

        .center {
            text-align: center;
        }

        .code {
            width: 15%;
            font-weight: 800;
        }

        .units {
            width: 10%;
        }

        .taken {
            width: 12%;
        }

        .prereq {
            width: 18%;
        }

        .nothing {
            text-align: center;
            font-weight: 700;
            font-style: italic;
        }

        .footer {
            position: fixed;
            right: 14mm;
            bottom: 12mm;
            left: 14mm;
            z-index: 1;
            border-top: 1px solid #111827;
            padding-top: 8px;
            color: #111827;
            font-size: 10px;
        }

        .validation-title {
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .validation-note {
            margin-top: 6px;
        }

        .seal {
            margin-top: 7px;
            font-weight: 800;
        }

        .generated {
            margin-top: 6px;
        }

        .status-failed {
            background: #fee2e2;
        }

        .empty {
            margin-top: 14px;
            border: 1px dashed #111827;
            padding: 24px;
            text-align: center;
        }
    </style>
</head>

<body>
    <main class="sheet">
        <div class="content">
            <header class="header">
                <div>
                    @if ($logoSrc)
                        <img src="{{ $logoSrc }}" alt="Campus logo" class="logo">
                    @else
                        <div class="logo-placeholder">LOGO</div>
                    @endif
                </div>
                <div>
                    <div class="republic">Republic of the Philippines</div>
                    <div class="university">UNIVERSITY OF SOUTHERN MINDANAO</div>
                    <div class="campus">{{ $campus['address'] ?: $campus['name'] }}</div>
                </div>
                <div></div>
            </header>

            <h1>STUDENT CURRICULUM</h1>

            <section class="student-grid">
                <div class="details">
                    <div class="label">Fullname</div>
                    <div>:</div>
                    <div class="value">{{ $studentName }}</div>
                    <div class="label">Student No.</div>
                    <div>:</div>
                    <div class="value">{{ $studentNo }}</div>
                    <div class="label">Program</div>
                    <div>:</div>
                    <div>{{ $profileValue('program', $profileValue('programName', $profileValue('progName'))) }}</div>
                    <div class="label">Major</div>
                    <div>:</div>
                    <div>{{ $profileValue('major', $profileValue('majorName')) }}</div>
                </div>
                <div class="details">
                    <div class="label">Campus</div>
                    <div>:</div>
                    <div>{{ $campus['name'] }}</div>
                    <div class="label">Year Level</div>
                    <div>:</div>
                    <div>{{ $profileValue('yearLevel', $profileValue('yearLevelName', $profileValue('yearLevelId'))) }}
                    </div>
                    <div class="label">Curriculum ID</div>
                    <div>:</div>
                    <div class="value">{{ $profileValue('curriculumId') }}</div>
                    <div class="label">Term ID</div>
                    <div>:</div>
                    <div>{{ $profileValue('termId') }}</div>
                </div>
            </section>


            @if ($curriculum['error'] ?? null)
                <div class="empty">{{ $curriculum['error'] }}</div>
            @elseif (count($years) === 0)
                <div class="empty">No curriculum records are currently available for this student's program.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th class="code">Code</th>
                            <th>Subject Title</th>
                            <th class="units center">Lec</th>
                            <th class="units center">Lab</th>
                            <th class="prereq">Prerequisite</th>
                            <th class="taken center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($years as $year)
                            <tr>
                                <td colspan="6"
                                    style="font-weight:bold; background:#e5e7eb; text-transform:uppercase;">
                                    {{ $year['year'] }}
                                </td>
                            </tr>

                            @foreach ($year['semesters'] as $semester)
                                <tr>
                                    <td colspan="6" style="font-weight:600; background:#f3f4f6;">
                                        {{ $semester['semester'] }}
                                        <span style="float:right;">
                                            {{ count($semester['rows']) }} subjects /
                                            {{ $formatUnit($semester['units']) }} units
                                        </span>
                                    </td>
                                </tr>

                                @foreach ($semester['rows'] as $row)
                                    <tr class="{{ $row['taken_status'] === 'Failed' ? 'status-failed' : '' }}">
                                        <td class="code">{{ $row['code'] }}</td>
                                        <td>{{ $row['description'] }}</td>
                                        <td class="center">{{ $formatUnit($row['lecture_units']) }}</td>
                                        <td class="center">{{ $formatUnit($row['lab_units']) }}</td>
                                        <td>
                                            @forelse ($row['prerequisites'] as $prerequisite)
                                                {{ $prerequisite }}@unless ($loop->last)
                                                ,
                                            @endunless
                                            @empty
                                                -
                                            @endforelse
                                        </td>
                                        <td class="center">{{ $row['taken_status'] ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endif
            <section class="summary">
                <div>Total Subjects</div>
                <div>:</div>
                <div class="center">{{ $totals['subjects'] }}</div>
                <div>Total Units</div>
                <div>:</div>
                <div class="center">{{ $formatUnit($totals['units']) }}</div>
                <div>Lecture Units</div>
                <div>:</div>
                <div class="center">{{ $formatUnit($totals['lecture_units']) }}</div>
                <div>Laboratory Units</div>
                <div>:</div>
                <div class="center">{{ $formatUnit($totals['lab_units']) }}</div>
                <div>Total Terms</div>
                <div>:</div>
                <div class="center">{{ $totals['semesters'] }}</div>
            </section>

        </div>

    </main>
</body>

</html>
