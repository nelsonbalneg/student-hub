<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PFT Result Report</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #0f172a;
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        h1 {
            margin: 0 0 4px;
            font-size: 18px;
        }
        .meta {
            margin-bottom: 14px;
            color: #475569;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #e2e8f0;
            color: #334155;
            font-size: 8px;
            text-align: left;
            text-transform: uppercase;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 5px;
            vertical-align: top;
        }
        .result-line {
            display: block;
            margin-bottom: 2px;
        }
        .muted {
            color: #64748b;
        }
    </style>
</head>
<body>
    <h1>PFT Result Report</h1>
    <div class="meta">
        Generated {{ $generatedAt->format('Y-m-d H:i:s') }} |
        Term: {{ $filters['term_id'] ?? 'All' }} |
        Campus: {{ $filters['campus_id'] ?? 'All' }} |
        College: {{ $filters['college_id'] ?? 'All' }} |
        Section: {{ $filters['section_id'] ?? 'All' }} |
        Test Type: {{ $filters['test_type_id'] ?? 'All' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Tested Date</th>
                <th>Term</th>
                <th>Campus</th>
                <th>College</th>
                <th>Section ID</th>
                <th>Section Name</th>
                <th>Year Level</th>
                <th>PFT Test Type</th>
                <th>Results</th>
                <th>Remarks</th>
                <th>Status</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['number'] }}</td>
                    <td>{{ $row['tested_date'] }}</td>
                    <td>{{ $row['term'] }}</td>
                    <td>{{ $row['campus'] }}</td>
                    <td>{{ $row['college'] }}</td>
                    <td>{{ $row['section_id'] }}</td>
                    <td>{{ $row['section_name'] }}</td>
                    <td>{{ $row['year_level'] }}</td>
                    <td>{{ $row['pft_test_type'] }}</td>
                    <td>
                        @forelse ($row['results'] as $line)
                            <span class="result-line"><strong>{{ $line['label'] }}:</strong> {{ $line['value'] }}</span>
                        @empty
                            <span class="muted">No result data</span>
                        @endforelse
                    </td>
                    <td>{{ $row['remarks'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['created_at'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="muted">No PFT results found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
