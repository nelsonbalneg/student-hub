<?php

namespace App\Http\Controllers\Society;

use App\Http\Controllers\Controller;
use App\Models\Society;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SocietyAnnouncementController extends Controller
{
    public function publicIndex()
    {
        return Inertia::render('Society/Student/Workspace', ['section' => 'announcements', 'society' => null]);
    }

    public function index(Society $society)
    {
        return Inertia::render('Society/Student/Workspace', ['section' => 'announcements', 'society' => $society->load('announcements')]);
    }

    public function adminIndex(Society $society)
    {
        return Inertia::render('Society/Admin/Details', ['section' => 'announcements', 'society' => $society->load('announcements')]);
    }
}
