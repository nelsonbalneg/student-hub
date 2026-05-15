<?php

namespace App\Http\Controllers\Society;

use App\Http\Controllers\Controller;
use App\Models\Society;
use App\Models\SocietyAccreditation;
use App\Models\SocietyAccreditationRequest;
use App\Models\SocietyMember;
use App\Models\SocietyMembership;
use App\Models\SocietyEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SocietyDashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Society/Admin/Dashboard', [
            'stats' => [
                'total_societies' => Society::count(),
                'accredited_societies' => SocietyAccreditationRequest::where('status', 'approved')->count(),
                'pending_applications' => SocietyAccreditationRequest::whereIn('status', ['submitted', 'under_review'])->count(),
                'expired_accreditations' => SocietyAccreditation::where('status', 'Expired')->count(),
                'total_members' => SocietyMember::where('status', 'active')->count(),
                'upcoming_events' => SocietyEvent::where('start_date', '>', now())->count(),
                'pending_events' => SocietyEvent::where('status', 'Submitted')->count(),
            ]
        ]);
    }

    public function societyIndex(Society $society)
    {
        return Inertia::render('Society/Manage/Dashboard', [
            'society' => $society->loadCount(['memberships', 'events', 'announcements']),
            'stats' => [
                'members' => $society->memberships()->where('status', 'Approved')->count(),
                'upcoming_events' => $society->events()->where('start_date', '>', now())->count(),
                'pending_members' => $society->memberships()->where('status', 'Pending')->count(),
            ]
        ]);
    }
}
