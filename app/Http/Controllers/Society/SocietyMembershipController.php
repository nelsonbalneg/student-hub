<?php

namespace App\Http\Controllers\Society;

use App\Http\Controllers\Controller;
use App\Models\Society;
use App\Models\SocietyMembership;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SocietyMembershipController extends Controller
{
    public function index(Society $society)
    {
        return Inertia::render('Society/Manage/Members', [
            'society' => $society,
            'memberships' => $society->memberships()->with('student')->paginate(20),
        ]);
    }

    public function join(Request $request, Society $society)
    {
        // Check if already a member
        if ($society->memberships()->where('student_id', auth()->id())->exists()) {
            return back()->with('error', 'You are already a member or have a pending request.');
        }

        $society->memberships()->create([
            'student_id' => auth()->id(),
            'status' => 'Pending',
            'joined_at' => now(),
        ]);

        return back()->with('success', 'Membership request sent.');
    }

    public function mySocieties()
    {
        return Inertia::render('Society/Student/MySocieties', [
            'memberships' => auth()->user()->societyMemberships()
                ->with('society.accreditations')
                ->get(),
        ]);
    }

    public function adminIndex()
    {
        return Inertia::render('Society/Admin/Members', [
            'memberships' => SocietyMembership::with(['society', 'student'])
                ->latest()
                ->paginate(15),
        ]);
    }
}
