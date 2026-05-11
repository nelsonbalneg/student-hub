<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendAnnouncementNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $announcement;

    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function handle(): void
    {
        $query = User::query()->where('is_active', true);

        if ($this->announcement->visibility !== 'public') {
            $targets = $this->announcement->targets;

            if ($targets->count() > 0) {
                $query->where(function ($q) use ($targets) {
                    foreach ($targets as $target) {
                        $q->orWhere(function ($sub) use ($target) {
                            if ($target->role_id) {
                                $sub->whereHas('roles', fn($r) => $r->where('id', $target->role_id));
                            }
                            if ($target->office_id) {
                                $sub->where('office', $target->office_id);
                            }
                            if ($target->department_id) {
                                $sub->where('department', $target->department_id);
                            }
                            if ($target->campus_id) {
                                $sub->where('campus_id', $target->campus_id);
                            }
                        });
                    }
                });
            } else {
                // Default visibility filters
                if ($this->announcement->visibility === 'students') {
                    $query->where('user_type', 'student');
                } elseif ($this->announcement->visibility === 'employees') {
                    $query->whereIn('user_type', ['staff', 'faculty']);
                } elseif ($this->announcement->visibility === 'faculty') {
                    $query->where('user_type', 'faculty');
                }
            }
        }

        $users = $query->get();

        Notification::send($users, new AnnouncementNotification($this->announcement));
    }
}
