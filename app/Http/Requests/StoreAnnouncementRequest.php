<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreAnnouncementRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'attachments' => $this->validAttachments(),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', Announcement::class);
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string|max:500',
            'content' => 'required|string',
            'category_id' => 'required|exists:announcement_categories,id',
            'priority' => 'required|in:low,normal,high,urgent',
            'visibility' => 'required|in:public,students,employees,faculty,specific_roles',
            'publish_at' => 'nullable|date',
            'expire_at' => 'nullable|date|after_or_equal:publish_at',
            'is_pinned' => 'boolean',
            'allow_comments' => 'boolean',
            'send_notification' => 'boolean',
            'status' => 'required|in:draft,scheduled,published,archived',
            'targets' => 'nullable|array',
            'targets.*.role_id' => 'nullable|exists:roles,id',
            'targets.*.office_id' => 'nullable|string',
            'targets.*.department_id' => 'nullable|string',
            'targets.*.campus_id' => 'nullable|integer',
            'targets.*.year_level' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip|max:10240',
        ];
    }

    private function validAttachments(): array
    {
        return collect($this->file('attachments', []))
            ->filter(fn ($file): bool => $file instanceof UploadedFile && $file->isValid() && (bool) $file->getRealPath())
            ->values()
            ->all();
    }
}
