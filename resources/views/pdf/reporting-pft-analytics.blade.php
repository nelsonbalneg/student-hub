@php
    $logoSrc = null;
    $logoPath = $logoPath ?? null;

    if ($logoPath && is_file($logoPath)) {
        $mimeType = mime_content_type($logoPath) ?: 'image/png';
        $logoSrc = 'data:'.$mimeType.';base64,'.base64_encode(file_get_contents($logoPath));
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PFT Analytics Report</title>
    <style>
        @page {
            size: A4;
            margin: 16mm 16mm 20mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            background: #ffffff;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.4;
        }
        .sheet {
            width: 100%;
            position: relative;
            z-index: 1;
        }
        .header {
            border-bottom: 2px solid #0f766e;
            padding-bottom: 12px;
            margin-bottom: 16px;
            text-align: center;
            position: relative;
            min-height: 72px;
        }
        .report-logo {
            position: absolute;
            top: 0;
            left: 0;
            width: 58px;
            height: 58px;
            object-fit: contain;
        }
        .report-logo-placeholder {
            position: absolute;
            top: 0;
            left: 0;
            width: 58px;
            height: 58px;
            border: 1px solid #99f6e4;
            border-radius: 999px;
            color: #0f766e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
        }
        .republic {
            font-size: 10px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .university {
            margin-top: 2px;
            font-size: 14px;
            font-weight: 500;
            color: #0f766e;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .campus {
            margin-top: 2px;
            font-size: 10px;
            color: #475569;
        }
        h1 {
            margin: 28px 0 10px;
            text-align: center;
            font-size: 16px;
            font-weight: 300;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #0f172a;
        }
        .report-subtitle {
            text-align: center;
            color: #64748b;
            font-size: 10px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .metadata-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 24px;
        }
        .meta-item {
            display: grid;
            grid-template-columns: 100px 8px 1fr;
            font-size: 10.5px;
        }
        .meta-label {
            color: #475569;
            font-weight: 700;
        }
        .meta-value {
            color: #0f172a;
            font-weight: 700;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 16px;
        }
        .report-table th {
            background: #0f766e;
            color: #ffffff;
            padding: 6px 10px;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.02em;
            border: 1px solid #0d9488;
        }
        .report-table td {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .report-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .component-row {
            background: #f1f5f9 !important;
            font-weight: 500;
        }
        .category-row {
            font-weight: 500;
        }
        .indent-1 {
            padding-left: 24px !important;
            color: #0f766e;
            font-weight: 400;
        }
        .indent-2 {
            padding-left: 44px !important;
            color: #475569;
            font-weight: 300;
        }
        .text-right {
            text-align: right !important;
        }
        .text-muted {
            color: #64748b;
        }
        .distribution-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .badge {
            display: inline-block;
            font-size: 8px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid transparent;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .college-section-title {
            margin: 18px 0 8px;
            padding: 8px 10px;
            background: #ecfdf5;
            border: 1px solid #99f6e4;
            border-left: 4px solid #0f766e;
            border-radius: 6px;
            color: #0f766e;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            page-break-after: avoid;
        }
        .college-section-title span {
            float: right;
            color: #475569;
            font-size: 9px;
            font-weight: 600;
            text-transform: none;
            letter-spacing: 0;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            color: #64748b;
            font-size: 8.5px;
            text-align: center;
        }
        
        /* PAGE 2 STYLES */
        .page-break {
            page-break-before: always;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }
        .chart-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px;
        }
        .chart-card-title {
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
            color: #0f766e;
            margin-bottom: 12px;
            letter-spacing: 0.03em;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
        }
        .chart-row {
            display: grid;
            grid-template-columns: 100px 1fr 65px;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .chart-row:last-child {
            margin-bottom: 0;
        }
        .chart-label {
            font-size: 8.5px;
            font-weight: 400;
            color: #475569;
            text-align: right;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chart-bar-wrapper {
            background: #e2e8f0;
            border-radius: 9999px;
            height: 8px;
            overflow: hidden;
        }
        .chart-bar {
            height: 100%;
            border-radius: 9999px;
        }
        .chart-value {
            font-size: 8.5px;
            font-weight: 500;
            color: #0f172a;
        }
        
        /* HIERARCHICAL CHARTS STYLES */
        .component-chart-group {
            margin-top: 20px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            page-break-inside: avoid;
        }
        .component-title-bg {
            font-size: 11px;
            font-weight: 600;
            color: #0f766e;
            margin: 0 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            border-bottom: 2px solid #0f766e;
            padding-bottom: 4px;
        }
        .category-chart-group {
            margin-left: 16px;
            margin-top: 12px;
            border-left: 2px solid #f1f5f9;
            padding-left: 12px;
        }
        .category-group-title {
            font-size: 9.5px;
            font-weight: 600;
            color: #0d9488;
            margin: 0 0 6px 0;
            text-transform: uppercase;
        }
        .test-types-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 8px;
        }
        .test-chart-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px;
        }
        .test-group-title {
            font-size: 8.5px;
            font-weight: 600;
            color: #475569;
            margin: 0 0 6px 0;
            text-transform: uppercase;
        }
        .chart-wrapper-box {
            margin-bottom: 4px;
        }
        .indent-chart-1 {
            margin-bottom: 8px;
        }
        .compact-row {
            grid-template-columns: 80px 1fr 55px !important;
            margin-bottom: 4px;
        }
        .super-compact-row {
            grid-template-columns: 70px 1fr 50px !important;
            margin-bottom: 3px;
        }
        .watermark-grid {
            position: fixed;
            inset: 0;
            z-index: 0;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-auto-rows: 90px;
            gap: 18px 8px;
            align-content: start;
            padding: 30px 0;
            pointer-events: none;
            overflow: hidden;
        }
        .watermark-grid span {
            color: #0f172a;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.18em;
            opacity: 0.045;
            text-align: center;
            text-transform: uppercase;
            transform: rotate(-28deg);
            white-space: nowrap;
        }
    </style>
</head>
<body>
    @php
        $campusLabel = $labels['campus'] ?? ($filters['campus_id'] ?? 'All Campuses');
        $termLabel = $labels['term'] ?? ($filters['term_id'] ?? 'All Terms');
        $collegeId = $filters['college_id'] ?? null;
        $sectionId = $filters['section_id'] ?? null;
        $collegeLabel = filled($collegeId)
            ? ($labels['colleges'][(string) $collegeId] ?? $collegeId)
            : 'All Colleges';
        $sectionLabel = filled($sectionId) ? $sectionId : 'All Sections';
    @endphp
    <div class="watermark-grid" aria-hidden="true">
        @for ($i = 0; $i < 45; $i++)
            <span>CONFIDENTIAL</span>
        @endfor
    </div>
    <div class="sheet">
        <header class="header">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="Report logo" class="report-logo">
            @else
                <div class="report-logo-placeholder">LOGO</div>
            @endif
            <div class="republic">Republic of the Philippines</div>
            <div class="university">University of Southern Mindanao</div>
            <div class="campus">Kabacan, Cotabato</div>
        </header>

        <h1>Physical Fitness Test Analytics</h1>
        <div class="report-subtitle">Component, Category, and Test Type Analysis</div>

        <section class="metadata-grid">
            <div class="meta-item">
                <span class="meta-label">Campus</span>
                <span>:</span>
                <span class="meta-value">{{ $campusLabel }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Academic Term</span>
                <span>:</span>
                <span class="meta-value">{{ $termLabel }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">College</span>
                <span>:</span>
                <span class="meta-value">{{ $collegeLabel }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Section</span>
                <span>:</span>
                <span class="meta-value">{{ $sectionLabel }}</span>
            </div>
            <div class="meta-item" style="grid-column: span 2;">
                <span class="meta-label">Generated At</span>
                <span>:</span>
                <span class="meta-value">{{ $generatedAt->format('F d, Y h:i A') }}</span>
            </div>
        </section>

        @php
            $hierarchyGroups = filled($collegeId)
                ? [['label' => null, 'results' => null, 'students' => null, 'hierarchy' => $hierarchy]]
                : ($collegeGroups ?? []);
        @endphp

        @forelse ($hierarchyGroups as $group)
            @if (filled($group['label'] ?? null))
                <div class="college-section-title">
                    {{ $group['label'] }}
                    <span>{{ $group['results'] ?? 0 }} results &middot; {{ $group['students'] ?? 0 }} students</span>
                </div>
            @endif

            <table class="report-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Metric / Test Name</th>
                        <th class="text-right" style="width: 110px;">Total Results</th>
                        <th class="text-right" style="width: 100px;">Students</th>
                        <th style="text-align: left;">Interpretation Distribution Breakdown</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($group['hierarchy'] as $component)
                        <!-- Component Row -->
                        <tr class="component-row">
                            <td>{{ $component['label'] }}</td>
                            <td class="text-right">{{ $component['value'] }}</td>
                            <td class="text-right">{{ $component['students'] }}</td>
                            <td>
                                <div class="distribution-bar">
                                    @foreach ($component['interpretations'] as $item)
                                        @php
                                            $color = $item['color'] ?? 'slate';
                                            $hex = [
                                                'emerald' => '#059669', 'green' => '#16a34a', 'lime' => '#65a30d',
                                                'amber' => '#f59e0b', 'orange' => '#f97316', 'red' => '#ef4444',
                                                'rose' => '#e11d48', 'slate' => '#64748b', 'blue' => '#2563eb',
                                                'violet' => '#7c3aed'
                                            ][$color] ?? '#64748b';
                                        @endphp
                                        <span class="badge" style="color: {{ $hex }}; border-color: {{ $hex }}44; background: {{ $hex }}14;">
                                            {{ $item['label'] }}: {{ $item['value'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                        </tr>

                        @foreach ($component['categories'] as $category)
                            <!-- Category Row -->
                            <tr class="category-row">
                                <td class="indent-1">{{ $category['label'] }}</td>
                                <td class="text-right">{{ $category['value'] }}</td>
                                <td class="text-right">{{ $category['students'] }}</td>
                                <td>
                                    <div class="distribution-bar">
                                        @foreach ($category['interpretations'] as $item)
                                            @php
                                                $color = $item['color'] ?? 'slate';
                                                $hex = [
                                                    'emerald' => '#059669', 'green' => '#16a34a', 'lime' => '#65a30d',
                                                    'amber' => '#f59e0b', 'orange' => '#f97316', 'red' => '#ef4444',
                                                    'rose' => '#e11d48', 'slate' => '#64748b', 'blue' => '#2563eb',
                                                    'violet' => '#7c3aed'
                                                ][$color] ?? '#64748b';
                                            @endphp
                                            <span class="badge" style="color: {{ $hex }}; border-color: {{ $hex }}44; background: {{ $hex }}14;">
                                                {{ $item['label'] }}: {{ $item['value'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>

                            @foreach ($category['test_types'] as $testType)
                                <!-- Test Type Row -->
                                <tr class="test-type-row">
                                    <td class="indent-2">{{ $testType['label'] }}</td>
                                    <td class="text-right text-muted">{{ $testType['value'] }}</td>
                                    <td class="text-right text-muted">{{ $testType['students'] }}</td>
                                    <td>
                                        <div class="distribution-bar">
                                            @foreach ($testType['interpretations'] as $item)
                                                @php
                                                    $color = $item['color'] ?? 'slate';
                                                    $hex = [
                                                        'emerald' => '#059669', 'green' => '#16a34a', 'lime' => '#65a30d',
                                                        'amber' => '#f59e0b', 'orange' => '#f97316', 'red' => '#ef4444',
                                                        'rose' => '#e11d48', 'slate' => '#64748b', 'blue' => '#2563eb',
                                                        'violet' => '#7c3aed'
                                                    ][$color] ?? '#64748b';
                                                @endphp
                                                <span class="badge" style="color: {{ $hex }}; border-color: {{ $hex }}44; background: {{ $hex }}14; font-size: 7.5px; padding: 1px 4px;">
                                                    {{ $item['label'] }}: {{ $item['value'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; color: #64748b; padding: 30px;">
                                No component analytics available for this college.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @empty
            <table class="report-table">
                <tbody>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #64748b; padding: 30px;">
                            No component analytics available for the selected filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        @endforelse

        <!-- PAGE 2: Charts (Page Break) -->
        <div class="page-break"></div>
        
        <header class="header">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="Report logo" class="report-logo">
            @else
                <div class="report-logo-placeholder">LOGO</div>
            @endif
            <div class="republic">Republic of the Philippines</div>
            <div class="university">University of Southern Mindanao</div>
            <div class="campus">Kabacan, Cotabato</div>
        </header>

        <h1>PFT Analytics Chart Summary</h1>
        <div class="report-subtitle">Visual performance indicators & distribution graphs</div>

        <div class="charts-grid">
            <!-- Graph 1: Interpretation Distribution -->
            <div class="chart-card">
                <div class="chart-card-title">Overall Interpretation Distribution</div>
                @php
                    $totalInt = $interpretationAnalytics['interpreted'] + $interpretationAnalytics['unclassified'];
                    $maxDistVal = collect($interpretationAnalytics['distribution'])->max('value') ?: 1;
                @endphp
                @forelse ($interpretationAnalytics['distribution'] as $item)
                    @php
                        $color = $item['color'] ?? 'slate';
                        $hex = [
                            'emerald' => '#059669', 'green' => '#16a34a', 'lime' => '#65a30d',
                            'amber' => '#f59e0b', 'orange' => '#f97316', 'red' => '#ef4444',
                            'rose' => '#e11d48', 'slate' => '#64748b', 'blue' => '#2563eb',
                            'violet' => '#7c3aed'
                        ][$color] ?? '#64748b';
                        $percent = $totalInt > 0 ? round(($item['value'] / $totalInt) * 100, 1) : 0;
                        $barWidth = $maxDistVal > 0 ? round(($item['value'] / $maxDistVal) * 100, 1) : 0;
                    @endphp
                    <div class="chart-row">
                        <div class="chart-label">{{ $item['label'] }}</div>
                        <div class="chart-bar-wrapper">
                            <div class="chart-bar" style="width: {{ $barWidth }}%; background-color: {{ $hex }};"></div>
                        </div>
                        <div class="chart-value">{{ $item['value'] }} ({{ $percent }}%)</div>
                    </div>
                @empty
                    <p class="text-muted" style="text-align: center; margin: 20px 0;">No interpretation distribution data available.</p>
                @endforelse
            </div>

            <!-- Graph 2: Components Count -->
            <div class="chart-card">
                <div class="chart-card-title">Results Count by Physical Fitness Component</div>
                @php
                    $maxComponentVal = collect($hierarchy)->max('value') ?: 1;
                    $totalResults = collect($hierarchy)->sum('value') ?: 1;
                @endphp
                @forelse ($hierarchy as $component)
                    @php
                        $barWidth = round(($component['value'] / $maxComponentVal) * 100, 1);
                        $percent = round(($component['value'] / $totalResults) * 100, 1);
                    @endphp
                    <div class="chart-row">
                        <div class="chart-label" title="{{ $component['label'] }}">{{ $component['label'] }}</div>
                        <div class="chart-bar-wrapper">
                            <div class="chart-bar" style="width: {{ $barWidth }}%; background-color: #0f766e;"></div>
                        </div>
                        <div class="chart-value">{{ $component['value'] }} ({{ $percent }}%)</div>
                    </div>
                @empty
                    <p class="text-muted" style="text-align: center; margin: 20px 0;">No component results count available.</p>
                @endforelse
            </div>
        </div>

        <!-- Hierarchical Breakdowns: Components, Categories, Test Types -->
        <div style="margin-top: 24px;">
            @php
                $colorsMap = [
                    'emerald' => '#059669', 'green' => '#16a34a', 'lime' => '#65a30d',
                    'amber' => '#f59e0b', 'orange' => '#f97316', 'red' => '#ef4444',
                    'rose' => '#e11d48', 'slate' => '#64748b', 'blue' => '#2563eb',
                    'violet' => '#7c3aed'
                ];
            @endphp

            @foreach ($hierarchy as $component)
                <div class="component-chart-group">
                    <h3 class="component-title-bg">{{ $component['label'] }} Breakdown</h3>
                    
                    <!-- Component Chart -->
                    <div class="chart-wrapper-box">
                        @php
                            $maxCompVal = collect($component['interpretations'])->max('value') ?: 1;
                            $totalComp = collect($component['interpretations'])->sum('value') ?: 1;
                        @endphp
                        @foreach ($component['interpretations'] as $item)
                            @php
                                $color = $item['color'] ?? 'slate';
                                $hex = $colorsMap[$color] ?? '#64748b';
                                $barWidth = round(($item['value'] / $maxCompVal) * 100, 1);
                                $percent = round(($item['value'] / $totalComp) * 100, 1);
                            @endphp
                            <div class="chart-row compact-row">
                                <div class="chart-label">{{ $item['label'] }}</div>
                                <div class="chart-bar-wrapper">
                                    <div class="chart-bar" style="width: {{ $barWidth }}%; background-color: {{ $hex }};"></div>
                                </div>
                                <div class="chart-value">{{ $item['value'] }} ({{ $percent }}%)</div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Categories under this component -->
                    @foreach ($component['categories'] as $category)
                        <div class="category-chart-group">
                            <h4 class="category-group-title">{{ $category['label'] }}</h4>
                            
                            <div class="chart-wrapper-box indent-chart-1">
                                @php
                                    $maxCatVal = collect($category['interpretations'])->max('value') ?: 1;
                                    $totalCat = collect($category['interpretations'])->sum('value') ?: 1;
                                @endphp
                                @foreach ($category['interpretations'] as $item)
                                    @php
                                        $color = $item['color'] ?? 'slate';
                                        $hex = $colorsMap[$color] ?? '#64748b';
                                        $barWidth = round(($item['value'] / $maxCatVal) * 100, 1);
                                        $percent = round(($item['value'] / $totalCat) * 100, 1);
                                    @endphp
                                    <div class="chart-row compact-row">
                                        <div class="chart-label">{{ $item['label'] }}</div>
                                        <div class="chart-bar-wrapper">
                                            <div class="chart-bar" style="width: {{ $barWidth }}%; background-color: {{ $hex }};"></div>
                                        </div>
                                        <div class="chart-value">{{ $item['value'] }} ({{ $percent }}%)</div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Test Types under this category -->
                            <div class="test-types-grid">
                                @foreach ($category['test_types'] as $testType)
                                    <div class="test-chart-card">
                                        <h5 class="test-group-title">{{ $testType['label'] }}</h5>
                                        <div class="chart-wrapper-box">
                                            @php
                                                $maxTestVal = collect($testType['interpretations'])->max('value') ?: 1;
                                                $totalTest = collect($testType['interpretations'])->sum('value') ?: 1;
                                            @endphp
                                            @foreach ($testType['interpretations'] as $item)
                                                @php
                                                    $color = $item['color'] ?? 'slate';
                                                    $hex = $colorsMap[$color] ?? '#64748b';
                                                    $barWidth = round(($item['value'] / $maxTestVal) * 100, 1);
                                                    $percent = round(($item['value'] / $totalTest) * 100, 1);
                                                @endphp
                                                <div class="chart-row super-compact-row">
                                                    <div class="chart-label">{{ $item['label'] }}</div>
                                                    <div class="chart-bar-wrapper">
                                                        <div class="chart-bar" style="width: {{ $barWidth }}%; background-color: {{ $hex }};"></div>
                                                    </div>
                                                    <div class="chart-value">{{ $item['value'] }} ({{ $percent }}%)</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        <footer class="footer">
            University of Southern Mindanao &middot; Kabacan, Cotabato &middot; Confidential Report
        </footer>
    </div>
</body>
</html>
