<?php

namespace App\Http\Controllers\Society;

use App\Http\Controllers\Controller;
use App\Models\Society;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SocietyEventController extends Controller
{
    public function publicIndex()
    {
        return Inertia::render('Society/Student/Workspace', ['section' => 'events', 'society' => null]);
    }

    public function index(Society $society)
    {
        return Inertia::render('Society/Student/Workspace', ['section' => 'events', 'society' => $society->load('events')]);
    }

    public function attendanceIndex(Society $society)
    {
        return Inertia::render('Society/Student/Workspace', ['section' => 'attendance', 'society' => $society->load('events')]);
    }

    public function adminIndex(Society $society)
    {
        return Inertia::render('Society/Admin/Details', ['section' => 'events', 'society' => $society->load('events')]);
    }

    public function adminAttendance(Society $society)
    {
        return Inertia::render('Society/Admin/Details', ['section' => 'attendance', 'society' => $society->load('events')]);
    }
}
