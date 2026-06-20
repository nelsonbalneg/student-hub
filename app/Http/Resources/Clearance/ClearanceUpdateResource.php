<?php

namespace App\Http\Resources\Clearance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClearanceUpdateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'purpose' => $this->purpose,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'status' => $this->status,
            'semester' => [
                'id' => $this->semester->id,
                'academic_year' => $this->semester->academic_year,
                'term' => $this->semester->term,
                'campus_id' => $this->semester->campus_id,
                'campus_name' => $this->semester->campus_name,
            ],
            'type' => [
                'id' => $this->type->id,
                'name' => $this->type->name,
                'audience' => $this->type->audience,
            ],
            'targeted_students' => $this->whenLoaded('targetedStudents', fn () => $this->targetedStudents->map(fn ($student) => [
                'id' => $student->id,
                'name' => $student->name,
                'student_no' => $student->student_no,
            ])->values()),
            'creator' => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ],
            'offices' => $this->whenLoaded('offices', function () {
                return $this->offices->map(fn ($o) => [
                    'id' => $o->id,
                    'office' => [
                        'id' => $o->office->id,
                        'name' => $o->office->name,
                    ],
                    'is_required' => $o->is_required,
                    'can_upload_accountability' => $o->can_upload_accountability,
                    'can_resolve_accountability' => $o->can_resolve_accountability,
                ]);
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
