<?php

namespace App\Http\Controllers\Admin\Reporting;

use App\Http\Controllers\Controller;
use App\Models\PftParq;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PftParqController extends Controller
{
    public function index(Request $request): Response
    {
        $request->user()->can('reporting.pft_result.view') || abort(403);

        $filters = $request->only(['search', 'campus', 'term', 'status']);
        $pageSize = $request->integer('per_page', 20);

        $selectedCampus = filled($filters['campus'] ?? null)
            ? SiteCampus::query()
                ->where('real_campus_id', (string) $filters['campus'])
                ->first()
            : null;

        if (filled($filters['term'] ?? null) && $selectedCampus) {
            $termBelongsToCampus = SiteAcademicTerm::query()
                ->where('site_campus_id', $selectedCampus->id)
                ->where('term_id', (string) $filters['term'])
                ->exists();

            if (! $termBelongsToCampus) {
                $filters['term'] = '';
            }
        }

        $baseQuery = PftParq::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('id_number', 'like', "%{$search}%");
                });
            })
            ->when($filters['campus'] ?? null, function ($query, $campusId) {
                $query->whereHas('user', function ($q) use ($campusId) {
                    $q->where('campus_id', $campusId);
                });
            })
            ->when($filters['term'] ?? null, function ($query, $termId) {
                $query->where('term_id', $termId);
            });

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'verified' => (clone $baseQuery)->where('clearance_status', 'verified')->count(),
            'pending_evaluation' => (clone $baseQuery)->where('clearance_status', 'pending_evaluation')->count(),
            'pending' => (clone $baseQuery)->where('clearance_status', 'pending')->count(),
        ];

        $query = (clone $baseQuery)
            ->with(['user', 'term.campus', 'verifier'])
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('clearance_status', $status);
            })
            ->orderByRaw("CASE WHEN clearance_status = 'pending_evaluation' THEN 0 ELSE 1 END")
            ->latest();

        $parqs = $query->paginate($pageSize)->withQueryString();

        // Get filter options
        $campuses = SiteCampus::query()
            ->whereNotNull('real_campus_id')
            ->where('real_campus_id', '<>', '')
            ->orderBy('campus_name')
            ->get()
            ->map(fn ($campus) => [
                'value' => (string) $campus->real_campus_id,
                'label' => $campus->campus_name,
            ]);

        // Get terms that belong to the selected campus.
        $terms = SiteAcademicTerm::query()
            ->select('term_id', 'school_year', 'semester')
            ->when($selectedCampus, fn ($query) => $query->where('site_campus_id', $selectedCampus->id))
            ->distinct()
            ->orderByDesc('school_year')
            ->orderByDesc('semester')
            ->get()
            ->map(fn ($term) => [
                'value' => $term->term_id,
                'label' => "AY {$term->school_year} - {$term->semester}",
            ]);

        $statuses = [
            ['value' => 'pending', 'label' => 'Pending (Clearance Required)'],
            ['value' => 'pending_evaluation', 'label' => 'Pending Evaluation'],
            ['value' => 'verified', 'label' => 'Verified'],
        ];

        return Inertia::render('Reporting/PftParq', [
            'parqs' => $parqs,
            'filters' => $filters,
            'stats' => $stats,
            'options' => [
                'campuses' => $campuses,
                'terms' => $terms,
                'statuses' => $statuses,
            ],
            'pageSizeOptions' => [20, 50, 100],
        ]);
    }

    public function verify(Request $request, PftParq $parq)
    {
        $request->user()->can('reporting.pft_result.view') || abort(403);

        $parq->update([
            'clearance_status' => 'verified',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        Log::info('PAR-Q medical clearance verified by admin.', [
            'admin_id' => $request->user()->id,
            'parq_id' => $parq->id,
            'student_id' => $parq->user_id,
        ]);

        return back()->with('success', 'Medical clearance verified successfully.');
    }

    public function reject(Request $request, PftParq $parq)
    {
        $request->user()->can('reporting.pft_result.view') || abort(403);

        // Usually, rejection means reverting it to pending so they can upload again.
        $parq->update([
            'clearance_status' => 'pending',
            'medical_clearance_path' => null, // Option: null out the path so they upload a new one
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        Log::info('PAR-Q medical clearance rejected by admin.', [
            'admin_id' => $request->user()->id,
            'parq_id' => $parq->id,
            'student_id' => $parq->user_id,
        ]);

        return back()->with('success', 'Medical clearance rejected. The student must upload a new document.');
    }

    public function updateStatus(Request $request, PftParq $parq)
    {
        $request->user()->can('reporting.pft_result.view') || abort(403);

        $validated = $request->validate([
            'status' => 'required|in:pending,pending_evaluation,verified',
        ]);

        $parq->update([
            'clearance_status' => $validated['status'],
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        Log::info('PAR-Q medical clearance status updated manually by admin.', [
            'admin_id' => $request->user()->id,
            'parq_id' => $parq->id,
            'student_id' => $parq->user_id,
            'new_status' => $validated['status'],
        ]);

        return back()->with('success', 'Medical clearance status updated successfully.');
    }
}
