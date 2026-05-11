<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AnnouncementActivityLog;
use App\Models\AnnouncementAttachment;
use App\Models\AnnouncementTarget;
use App\Jobs\SendAnnouncementNotifications;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnnouncementService
{
    public function createAnnouncement(array $data, $user)
    {
        return DB::transaction(function () use ($data, $user) {
            $announcement = Announcement::create(array_merge($data, [
                'created_by' => $user->id,
                'status' => $data['status'] ?? 'draft',
            ]));

            if (!empty($data['targets'])) {
                $this->syncTargets($announcement, $data['targets']);
            }

            if (!empty($data['attachments'])) {
                $this->handleAttachments($announcement, $data['attachments'], $user);
            }

            if ($announcement->status === 'published' && $announcement->send_notification) {
                SendAnnouncementNotifications::dispatch($announcement);
            }

            $this->logActivity($user, 'Created', "Created announcement: {$announcement->title}", $announcement);

            return $announcement;
        });
    }

    public function updateAnnouncement(Announcement $announcement, array $data, $user)
    {
        return DB::transaction(function () use ($announcement, $data, $user) {
            $announcement->update(array_merge($data, [
                'updated_by' => $user->id,
            ]));

            if (!empty($data['targets'])) {
                $this->syncTargets($announcement, $data['targets']);
            }

            if (!empty($data['attachments'])) {
                $this->handleAttachments($announcement, $data['attachments'], $user);
            }

            if (!empty($data['remove_attachments'])) {
                $this->removeAttachments($data['remove_attachments']);
            }

            $this->logActivity($user, 'Edited', "Updated announcement: {$announcement->title}", $announcement);

            return $announcement;
        });
    }

    protected function syncTargets(Announcement $announcement, array $targets)
    {
        $announcement->targets()->delete();
        foreach ($targets as $target) {
            $announcement->targets()->create($target);
        }
    }

    protected function handleAttachments(Announcement $announcement, array $files, $user)
    {
        $path = 'announcements/' . now()->format('Y/m');

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid() || ! $file->getRealPath()) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
            
            $filePath = Storage::disk('public')->putFileAs($path, $file, $fileName);

            if (!$filePath) {
                continue;
            }

            AnnouncementAttachment::create([
                'announcement_id' => $announcement->id,
                'original_name' => $originalName,
                'file_name' => basename($filePath),
                'file_path' => $filePath,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);
        }
    }

    protected function removeAttachments(array $ids)
    {
        $attachments = AnnouncementAttachment::whereIn('id', $ids)->get();
        foreach ($attachments as $attachment) {
            $path = trim($attachment->file_path);
            if (!empty($path)) {
                Storage::disk('public')->delete($path);
            }
            $attachment->delete();
        }
    }

    public function logActivity($user, $action, $description, $model = null)
    {
        AnnouncementActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
        ]);
    }
}
