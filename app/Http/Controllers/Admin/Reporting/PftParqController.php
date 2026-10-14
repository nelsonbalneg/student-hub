<?php

namespace App\Http\Controllers\Admin\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePftHealthQuestionnaireRequest;
use App\Models\PftHealthQuestionnaire;
use App\Models\PftMedicalCondition;
use App\Models\PftParq;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PftParqController extends Controller
{
    public function index(Request $request): Response
    {
        $request->user()->can('reporting.pft_result.view') || abort(403);

        $filters = $request->only(['search', 'campus', 'term', 'status', 'tab']);
        $filters['tab'] = $request->input('tab') === 'health' ? 'health' : 'parq';
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

        $healthQuestionnaires = PftHealthQuestionnaire::query()
            ->with('user')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas('user', fn ($userQuery) => $userQuery
                ->where(fn ($names) => $names->where('name', 'like', "%{$search}%")->orWhere('id_number', 'like', "%{$search}%"))))
            ->when($filters['campus'] ?? null, fn ($query, $campus) => $query->whereHas('user', fn ($users) => $users->where('campus_id', $campus)))
            ->when($filters['term'] ?? null, fn ($query, $term) => $query->where('term_id', $term))
            ->latest()
            ->paginate($pageSize)
            ->withQueryString();

        $healthTerms = SiteAcademicTerm::query()
            ->with('campus')
            ->whereIn('term_id', $healthQuestionnaires->pluck('term_id'))
            ->get();
        $healthQuestionnaires->getCollection()->each(function ($questionnaire) use ($healthTerms) {
            $questionnaire->setRelation('term', $healthTerms->first(fn ($term) => (string) $term->term_id === (string) $questionnaire->term_id
                && (string) $term->campus?->real_campus_id === (string) $questionnaire->user?->campus_id
            ));
        });

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
            'healthQuestionnaires' => $healthQuestionnaires,
            'filters' => $filters,
            'stats' => $stats,
            'options' => [
                'medicalConditions' => PftMedicalCondition::query()->where('is_active', true)->orderBy('sort_order')->pluck('name')->all(),
                'campuses' => $campuses,
                'terms' => $terms,
                'statuses' => $statuses,
            ],
            'pageSizeOptions' => [20, 50, 100],
        ]);
    }

    public function destroyHealth(Request $request, PftHealthQuestionnaire $questionnaire): RedirectResponse
    {
        abort_unless($request->user()->can('reporting.pft_result.view'), 403);
        $questionnaire->delete();
        Log::info('PFT health questionnaire deleted by admin.', ['admin_id' => $request->user()->id, 'questionnaire_id' => $questionnaire->id]);

        return back()->with('success', 'Health submission deleted.');
    }

    public function updateHealth(UpdatePftHealthQuestionnaireRequest $request, PftHealthQuestionnaire $questionnaire): RedirectResponse
    {
        $data = $request->validated();
        if ($data['clearance_granted'] && ! $questionnaire->medical_clearance_path) {
            throw ValidationException::withMessages(['clearance_granted' => 'A medical certificate must be uploaded before granting clearance.']);
        }
        $data['clearance_granted_by'] = $data['clearance_granted'] ? $request->user()->id : null;
        $data['clearance_granted_at'] = $data['clearance_granted'] ? now() : null;
        $questionnaire->update($data);
        Log::info('PFT health questionnaire updated by admin.', ['admin_id' => $request->user()->id, 'questionnaire_id' => $questionnaire->id, 'clearance_granted' => $data['clearance_granted']]);

        return back()->with('success', 'Health submission updated.');
    }

    public function destroy(Request $request, PftParq $parq): RedirectResponse
    {
        $request->user()->can('reporting.pft_result.view') || abort(403);

        $parq->delete();

        Log::info('PAR-Q entry deleted by admin.', [
            'admin_id' => $request->user()->id,
            'parq_id' => $parq->id,
            'student_id' => $parq->user_id,
        ]);

        return back()->with('success', 'PAR-Q entry deleted successfully.');
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
