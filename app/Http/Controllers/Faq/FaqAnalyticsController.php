<?php

namespace App\Http\Controllers\Faq;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\FaqSearchLog;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class FaqAnalyticsController extends Controller
{
    public function index()
    {
        $this->authorize('view-analytics', Faq::class);

        $mostViewed = Faq::orderByDesc('view_count')->take(10)->get(['id', 'question', 'view_count']);
        
        $searchTrends = FaqSearchLog::select('keyword', DB::raw('count(*) as count'), DB::raw('avg(result_count) as avg_results'))
            ->groupBy('keyword')
            ->orderByDesc('count')
            ->take(10)
            ->get();

        $categoryStats = FaqCategory::withCount('faqs')->get(['id', 'name', 'faqs_count']);

        $helpfulnessStats = [
            'helpful' => Faq::sum('helpful_count'),
            'not_helpful' => Faq::sum('not_helpful_count'),
        ];

        return Inertia::render('Faq/Admin/Analytics', [
            'mostViewed' => $mostViewed,
            'searchTrends' => $searchTrends,
            'categoryStats' => $categoryStats,
            'helpfulnessStats' => $helpfulnessStats,
        ]);
    }
}
